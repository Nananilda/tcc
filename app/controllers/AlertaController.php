<?php
/**
 * Controller: AlertaController
 * Faz a ponte entre a view de alertas e o AlertaModel.
 * Segue o mesmo padrão do SensorController.php.
 */

require_once __DIR__ . '/../models/Alerta.php';

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
     * Analisa $_POST e chama a ação adequada (somente admin resolve alertas).
     * Retorna ['mensagem' => string, 'erros' => array].
     */
    public function processarRequisicao(): array
    {
        $mensagem = '';
        $erros = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return compact('mensagem', 'erros');
        }

        $acao = $_POST['acao'] ?? '';

        if (!validarCSRF($_POST['csrf_token'] ?? '')) {
            $erros[] = 'Token de segurança inválido.';
            return compact('mensagem', 'erros');
        }

        if ($acao === 'resolver') {
            [$mensagem, $erros] = $this->resolver();
        }

        return compact('mensagem', 'erros');
    }

    private function resolver(): array
    {
        $mensagem = '';
        $erros = [];

        if (!$this->ehAdmin) {
            $erros[] = 'Acesso negado.';
            return [$mensagem, $erros];
        }

        $alertaId = (int) ($_POST['alerta_id'] ?? 0);
        if ($alertaId <= 0) {
            $erros[] = 'ID de alerta inválido.';
            return [$mensagem, $erros];
        }

        try {
            $this->model->marcarResolvido($alertaId);
            $this->registrarLog('ALERTA_RESOLVIDO', "Alerta ID $alertaId marcado como resolvido");
            $mensagem = 'Alerta marcado como resolvido.';
        } catch (PDOException $e) {
            $erros[] = 'Erro ao atualizar alerta: ' . $e->getMessage();
        }

        return [$mensagem, $erros];
    }

    // ── Consulta pública (usada pela view) ───────────────────────────────────

    /**
     * Retorna os alertas cadastrados no banco para exibição.
     * Em caso de erro (tabela inexistente etc.) devolve array vazio.
     */
    public function listarAlertas(): array
    {
        try {
            return $this->model->listarRecentes();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Retorna a contagem de alertas por severidade a partir do banco.
     */
    public function contarPorSeveridade(): array
    {
        try {
            return $this->model->contarPorSeveridade();
        } catch (PDOException $e) {
            return ['critico' => 0, 'atencao' => 0, 'info' => 0];
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
