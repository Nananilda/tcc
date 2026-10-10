<?php
/**
 * app/controllers/AlertaController.php
 * Controller: AlertaController
 * Faz a ponte entre a tela de alertas / API e o AlertaModel.
 * Segue o mesmo padrão do SensorController.php.
 */

require_once __DIR__ . '/../models/AlertaModel.php';
require_once __DIR__ . '/../models/SensorModel.php';

class AlertaController
{
    private AlertaModel $model;
    private PDO $pdo;
    private bool $ehAdmin;

    public function __construct(PDO $pdo, bool $ehAdmin)
    {
        $this->pdo = $pdo;
        $this->model = new AlertaModel($pdo);
        $this->ehAdmin = $ehAdmin;
    }

    // ── Despacho principal ───────────────────────────────────────────────────

    /**
     * Entrada para formulários web: confere o método (POST) e o token CSRF e
     * executa a ação pedida em $_POST['acao'] (somente admin cria/resolve alertas).
     *
     * @return array{mensagem:string,erros:string[]}
     */
    public function processarRequisicao(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['mensagem' => '', 'erros' => []];
        }

        if (!validarCSRF($_POST['csrf_token'] ?? '')) {
            return ['mensagem' => '', 'erros' => ['Token de segurança inválido.']];
        }

        return $this->executar($_POST['acao'] ?? '', $_POST);
    }

    /**
     * Executa uma ação sobre alertas. Reutilizado pela API.
     *
     * @param string $acao  'resolver' ou 'criar'
     * @param array  $dados campos da ação (alerta_id; ou sensor_id, severidade, mensagem, valor)
     * @return array{mensagem:string,erros:string[],id?:int}
     */
    public function executar(string $acao, array $dados): array
    {
        switch ($acao) {
            case 'resolver':
                [$mensagem, $erros] = $this->resolver($dados);
                return compact('mensagem', 'erros');

            case 'criar':
                return $this->criar($dados);

            default:
                return ['mensagem' => '', 'erros' => ['Ação desconhecida.']];
        }
    }

    /**
     * Cria um alerta manualmente (somente admin). Cobre o que os limites
     * automáticos não enxergam: manutenção programada, aviso da equipe, ocorrência
     * observada no chão de fábrica etc.
     *
     * @param array $dados sensor_id, severidade, mensagem e valor (opcional; aceita vírgula)
     * @return array{mensagem:string,erros:string[],id?:int}
     */
    private function criar(array $dados): array
    {
        if (!$this->ehAdmin) {
            return ['mensagem' => '', 'erros' => ['Acesso negado.']];
        }

        $sensorId = (int) ($dados['sensor_id'] ?? 0);
        $severidade = (string) ($dados['severidade'] ?? '');
        $mensagem = trim((string) ($dados['mensagem'] ?? ''));
        $valorInformado = $dados['valor'] ?? null;
        $valor = null;

        $erros = [];
        if ($sensorId <= 0) {
            $erros[] = 'Selecione o sensor do alerta.';
        }
        if (!AlertaModel::severidadeValida($severidade)) {
            $erros[] = 'Severidade inválida.';
        }
        $tamanho = mb_strlen($mensagem, 'UTF-8');
        if ($tamanho < 3) {
            $erros[] = 'A mensagem do alerta deve ter ao menos 3 caracteres.';
        } elseif ($tamanho > 200) {
            $erros[] = 'A mensagem do alerta é muito longa (máx 200 caracteres).';
        }
        // O valor é opcional, mas, se vier preenchido, precisa ser um número
        if ($valorInformado !== null && trim((string) $valorInformado) !== '') {
            $valor = lerNumero($valorInformado);
            if ($valor === null) {
                $erros[] = 'O valor medido deve ser um número (ex.: 36,5) ou ficar em branco.';
            }
        }
        if (!empty($erros)) {
            return ['mensagem' => '', 'erros' => $erros];
        }

        try {
            if (!(new SensorModel($this->pdo))->buscarPorId($sensorId)) {
                return ['mensagem' => '', 'erros' => ['Sensor não encontrado.']];
            }

            $id = $this->model->criar($sensorId, $severidade, $mensagem, $valor);
            $this->registrarLog('ALERTA_CRIADO', "Alerta ID $id ($severidade) criado manualmente no sensor $sensorId");
            return ['mensagem' => 'Alerta criado com sucesso.', 'erros' => [], 'id' => $id];
        } catch (PDOException $e) {
            error_log('[' . APP_NOME . '] Erro ao criar alerta: ' . $e->getMessage());
            return ['mensagem' => '', 'erros' => ['Não foi possível criar o alerta. Tente novamente.']];
        }
    }

    private function resolver(array $dados): array
    {
        $mensagem = '';
        $erros = [];

        if (!$this->ehAdmin) {
            return [$mensagem, ['Acesso negado.']];
        }

        $alertaId = (int) ($dados['alerta_id'] ?? 0);
        if ($alertaId <= 0) {
            return [$mensagem, ['ID de alerta inválido.']];
        }

        try {
            $alerta = $this->model->buscarPorId($alertaId);
            if (!$alerta) {
                return [$mensagem, ['Alerta não encontrado.']];
            }
            if ((int) $alerta['resolvido'] === 1) {
                return [$mensagem, ['Este alerta já foi resolvido.']];
            }

            $usuarioId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
            $this->model->marcarResolvido($alertaId, $usuarioId);
            $this->registrarLog('ALERTA_RESOLVIDO', "Alerta ID $alertaId marcado como resolvido");
            $mensagem = 'Alerta marcado como resolvido.';
        } catch (PDOException $e) {
            error_log('[' . APP_NOME . '] Erro ao resolver alerta: ' . $e->getMessage());
            $erros[] = 'Não foi possível atualizar o alerta. Tente novamente.';
        }

        return [$mensagem, $erros];
    }

    // ── Consulta pública (usada pelas telas e pela API) ──────────────────────

    /**
     * Retorna os alertas cadastrados no banco para exibição.
     * Em caso de erro devolve array vazio (e registra o motivo no log).
     */
    public function listarAlertas(): array
    {
        try {
            return $this->model->listarRecentes();
        } catch (PDOException $e) {
            error_log('[' . APP_NOME . '] Erro ao listar alertas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retorna a contagem de alertas pendentes por severidade a partir do banco.
     */
    public function contarPorSeveridade(): array
    {
        try {
            return $this->model->contarPorSeveridade();
        } catch (PDOException $e) {
            error_log('[' . APP_NOME . '] Erro ao contar alertas: ' . $e->getMessage());
            return ['critico' => 0, 'atencao' => 0, 'info' => 0];
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function registrarLog(string $acao, string $detalhe): void
    {
        if (function_exists('registrarLog')) {
            registrarLog($this->pdo, $_SESSION['usuario_id'] ?? null, $acao, $detalhe);
        }
    }
}
