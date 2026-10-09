<?php
/**
 * app/controllers/SensorController.php
 * Controller: SensorController
 * Faz a ponte entre as requisições (telas web e API) e o SensorModel.
 * Inclui validação, controle de acesso e log de ações.
 *
 * Existem duas portas de entrada para as mesmas regras:
 *   - processarRequisicao(): formulários web (POST + token CSRF);
 *   - executar():            usado diretamente pela API (api/sensores.php).
 */

require_once __DIR__ . '/../models/SensorModel.php';

class SensorController
{
    private SensorModel $model;
    private PDO $pdo;
    private bool $ehAdmin;

    public function __construct(PDO $pdo, bool $ehAdmin)
    {
        $this->pdo = $pdo;
        $this->model = new SensorModel($pdo);
        $this->ehAdmin = $ehAdmin;
    }

    // ── Despacho principal ───────────────────────────────────────────────────

    /**
     * Entrada para formulários web: confere o método (POST) e o token CSRF e
     * executa a ação pedida em $_POST['acao'].
     *
     * @return array{mensagem:string,erros:string[]}
     */
    public function processarRequisicao(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['mensagem' => '', 'erros' => []];
        }

        // Validação CSRF única para qualquer POST
        if (!validarCSRF($_POST['csrf_token'] ?? '')) {
            return ['mensagem' => '', 'erros' => ['Token de segurança inválido.']];
        }

        return $this->executar($_POST['acao'] ?? '', $_POST);
    }

    /**
     * Executa uma ação de gestão de sensores. Reutilizado pela API.
     *
     * @param string $acao  'cadastrar' ou 'toggle_status'
     * @param array  $dados campos da ação (nome, tipo, localizacao, status, sensor_id, novo_status)
     * @return array{mensagem:string,erros:string[]}
     */
    public function executar(string $acao, array $dados): array
    {
        switch ($acao) {
            case 'cadastrar':
                [$mensagem, $erros] = $this->cadastrar($dados);
                break;

            case 'toggle_status':
                [$mensagem, $erros] = $this->toggleStatus($dados);
                break;

            default:
                return ['mensagem' => '', 'erros' => ['Ação desconhecida.']];
        }

        return compact('mensagem', 'erros');
    }

    // ── Ações ────────────────────────────────────────────────────────────────

    /**
     * Valida e cadastra um novo sensor (somente admin).
     */
    private function cadastrar(array $dados): array
    {
        $mensagem = '';
        $erros = [];

        if (!$this->ehAdmin) {
            return [$mensagem, ['Acesso negado.']];
        }

        $nome = trim($dados['nome'] ?? '');
        $tipo = trim($dados['tipo'] ?? '');
        $localizacao = trim($dados['localizacao'] ?? '');
        $status = $dados['status'] ?? 'ativo';

        // Validações
        $tamanhoNome = function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome);
        if ($tamanhoNome < 3) {
            $erros[] = 'Nome do sensor deve ter ao menos 3 caracteres.';
        }
        if ($tamanhoNome > 100) {
            $erros[] = 'Nome do sensor muito longo (máx 100 caracteres).';
        }
        if (!SensorModel::tipoValido($tipo)) {
            $erros[] = 'Tipo de sensor inválido.';
        }
        if (!in_array($status, SensorModel::STATUS, true)) {
            $erros[] = 'Status inválido.';
        }
        if (strlen($localizacao) > 150) {
            $erros[] = 'Localização muito longa (máx 150 caracteres).';
        }

        if (!empty($erros)) {
            return [$mensagem, $erros];
        }

        try {
            $id = $this->model->cadastrar([
                'nome' => $nome,
                'tipo' => $tipo,
                'localizacao' => $localizacao ?: null,
                'status' => $status,
            ]);

            $this->registrarLog('SENSOR_CADASTRO', "Sensor ID $id: $nome ($tipo)");
            $mensagem = "Sensor \"$nome\" cadastrado com sucesso.";
        } catch (PDOException $e) {
            $erros[] = $this->erroDeBanco('salvar o sensor', $e);
        }

        return [$mensagem, $erros];
    }

    /**
     * Define o status de um sensor (ativo/inativo) — somente admin.
     */
    private function toggleStatus(array $dados): array
    {
        $mensagem = '';
        $erros = [];

        if (!$this->ehAdmin) {
            return [$mensagem, ['Acesso negado.']];
        }

        $sensorId = (int) ($dados['sensor_id'] ?? 0);
        $novoStatus = $dados['novo_status'] ?? '';

        if ($sensorId <= 0) {
            return [$mensagem, ['ID de sensor inválido.']];
        }

        if (!in_array($novoStatus, SensorModel::STATUS, true)) {
            return [$mensagem, ['Status inválido.']];
        }

        try {
            // Confirma que o sensor existe antes de atualizar
            if (!$this->model->buscarPorId($sensorId)) {
                return [$mensagem, ['Sensor não encontrado.']];
            }

            $this->model->atualizarStatus($sensorId, $novoStatus);
            $this->registrarLog('SENSOR_STATUS', "Sensor ID $sensorId → $novoStatus");
            $mensagem = "Status do sensor atualizado para \"$novoStatus\".";
        } catch (PDOException $e) {
            $erros[] = $this->erroDeBanco('alterar o status do sensor', $e);
        }

        return [$mensagem, $erros];
    }

    // ── Consulta pública (usada pelas telas e pela API) ──────────────────────

    /**
     * Retorna todos os sensores para exibição.
     * Em caso de erro devolve array vazio e acrescenta uma mensagem em $erros.
     */
    public function listarSensores(array &$erros = []): array
    {
        try {
            return $this->model->listarTodos();
        } catch (PDOException $e) {
            $erros[] = $this->erroDeBanco('consultar os sensores', $e);
            return [];
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function registrarLog(string $acao, string $detalhe): void
    {
        if (function_exists('registrarLog')) {
            registrarLog($this->pdo, $_SESSION['usuario_id'] ?? null, $acao, $detalhe);
        }
    }

    /**
     * Grava o erro técnico no log do servidor e devolve uma mensagem genérica
     * para o usuário (não expor detalhes do SQL na tela).
     */
    private function erroDeBanco(string $acao, PDOException $e): string
    {
        error_log('[' . APP_NOME . '] Erro ao ' . $acao . ': ' . $e->getMessage());
        return "Não foi possível $acao. Tente novamente.";
    }
}
