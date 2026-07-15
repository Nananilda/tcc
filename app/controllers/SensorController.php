<?php
/**
 * Controller: SensorController
 * Faz a ponte entre as requisições HTTP (view) e o SensorModel.
 * Inclui validação, controle de acesso e log de ações.
 */

require_once __DIR__ . '/../models/Sensor.php';

class SensorController
{
    private SensorModel $model;
    private PDO         $pdo;
    private bool        $ehAdmin;

    /** Tipos aceitos e seus rótulos de exibição. */
    public const TIPOS_VALIDOS = [
        'temperatura'  => 'Temperatura',
        'ruido'        => 'Ruído',
        'qualidade_ar' => 'Qualidade do Ar',
        'umidade'      => 'Umidade',
        'pressao'      => 'Pressão',
        'uv'           => 'UV',
    ];

    public function __construct(PDO $pdo, bool $ehAdmin)
    {
        $this->pdo     = $pdo;
        $this->model   = new SensorModel($pdo);
        $this->ehAdmin = $ehAdmin;
    }

    // ── Despacho principal ───────────────────────────────────────────────────

    /**
     * Analisa $_POST e chama a ação adequada.
     * Retorna ['mensagem' => string, 'erros' => array].
     */
    public function processarRequisicao(): array
    {
        $mensagem = '';
        $erros    = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return compact('mensagem', 'erros');
        }

        $acao = $_POST['acao'] ?? '';

        // Validação CSRF única para qualquer POST
        if (!validarCSRF($_POST['csrf_token'] ?? '')) {
            $erros[] = 'Token de segurança inválido.';
            return compact('mensagem', 'erros');
        }

        switch ($acao) {
            case 'cadastrar':
                [$mensagem, $erros] = $this->cadastrar();
                break;

            case 'toggle_status':
                [$mensagem, $erros] = $this->toggleStatus();
                break;

            default:
                // Ação desconhecida — ignorar silenciosamente
                break;
        }

        return compact('mensagem', 'erros');
    }

    // ── Ações ────────────────────────────────────────────────────────────────

    /**
     * Valida e cadastra um novo sensor (somente admin).
     */
    private function cadastrar(): array
    {
        $mensagem = '';
        $erros    = [];

        if (!$this->ehAdmin) {
            $erros[] = 'Acesso negado.';
            return [$mensagem, $erros];
        }

        $nome       = trim($_POST['nome']       ?? '');
        $tipo       = trim($_POST['tipo']       ?? '');
        $localizacao = trim($_POST['localizacao'] ?? '');
        $status     = $_POST['status']           ?? 'ativo';

        // Validações
        if (strlen($nome) < 3) {
            $erros[] = 'Nome do sensor deve ter ao menos 3 caracteres.';
        }
        if (!array_key_exists($tipo, self::TIPOS_VALIDOS)) {
            $erros[] = 'Tipo de sensor inválido.';
        }
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $erros[] = 'Status inválido.';
        }

        if (!empty($erros)) {
            return [$mensagem, $erros];
        }

        try {
            $id = $this->model->cadastrar([
                'nome'        => $nome,
                'tipo'        => $tipo,
                'localizacao' => $localizacao ?: null,
                'status'      => $status,
            ]);

            $this->registrarLog('SENSOR_CADASTRO', "Sensor ID $id: $nome ($tipo)");

            $mensagem = 'Sensor <strong>' . htmlspecialchars($nome, ENT_QUOTES) . '</strong> cadastrado com sucesso.';

        } catch (PDOException $e) {
            $erros[] = 'Erro ao salvar sensor: ' . $e->getMessage();
        }

        return [$mensagem, $erros];
    }

    /**
     * Alterna o status de um sensor entre ativo/inativo (somente admin).
     */
    private function toggleStatus(): array
    {
        $mensagem = '';
        $erros    = [];

        if (!$this->ehAdmin) {
            $erros[] = 'Acesso negado.';
            return [$mensagem, $erros];
        }

        $sensorId  = (int) ($_POST['sensor_id']   ?? 0);
        $novoStatus = $_POST['novo_status']        ?? '';

        if ($sensorId <= 0) {
            $erros[] = 'ID de sensor inválido.';
            return [$mensagem, $erros];
        }

        if (!in_array($novoStatus, ['ativo', 'inativo'], true)) {
            $erros[] = 'Status inválido.';
            return [$mensagem, $erros];
        }

        // Confirma que o sensor existe antes de atualizar
        $sensor = $this->model->buscarPorId($sensorId);
        if (!$sensor) {
            $erros[] = 'Sensor não encontrado.';
            return [$mensagem, $erros];
        }

        try {
            $this->model->atualizarStatus($sensorId, $novoStatus);
            $this->registrarLog('SENSOR_STATUS', "Sensor ID $sensorId → $novoStatus");
            $mensagem = 'Status do sensor atualizado para <strong>' . htmlspecialchars($novoStatus) . '</strong>.';

        } catch (PDOException $e) {
            $erros[] = 'Erro ao alterar status: ' . $e->getMessage();
        }

        return [$mensagem, $erros];
    }

    // ── Consulta pública (usada pela view) ───────────────────────────────────

    /**
     * Retorna todos os sensores para exibição na view.
     * Em caso de erro (tabela inexistente etc.) devolve array vazio.
     */
    public function listarSensores(array &$erros = []): array
    {
        try {
            return $this->model->listarTodos();
        } catch (PDOException $e) {
            $erros[] = 'Tabela de sensores não encontrada.';
            return [];
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function registrarLog(string $acao, string $detalhe): void
    {
        if (function_exists('registrarLog')) {
            registrarLog($this->pdo, $_SESSION['usuario_id'], $acao, $detalhe);
        }
    }
}
