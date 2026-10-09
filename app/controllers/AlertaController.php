<?php
/**
 * app/controllers/AlertaController.php
 * Controller: AlertaController
 * Faz a ponte entre a tela de alertas / API e o AlertaModel.
 * Segue o mesmo padrão do SensorController.php.
 */

require_once __DIR__ . '/../models/AlertaModel.php';

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
     * executa a ação pedida em $_POST['acao'] (somente admin resolve alertas).
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
     * @param string $acao  atualmente apenas 'resolver'
     * @param array  $dados campos da ação (alerta_id)
     * @return array{mensagem:string,erros:string[]}
     */
    public function executar(string $acao, array $dados): array
    {
        if ($acao !== 'resolver') {
            return ['mensagem' => '', 'erros' => ['Ação desconhecida.']];
        }

        [$mensagem, $erros] = $this->resolver($dados);
        return compact('mensagem', 'erros');
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
