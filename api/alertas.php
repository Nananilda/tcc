<?php
/**
 * api/alertas.php
 *
 * GET  -> lista alertas recentes + contagem por severidade.
 * POST -> acao=resolver (SOMENTE ADMIN). Body: { "acao": "resolver", "alerta_id": N }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();

require_once __DIR__ . '/../app/controllers/AlertaController.php';

$ehAdmin    = ehAdmin();
$controller = new AlertaController($pdo, $ehAdmin);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'sucesso'   => true,
        'alertas'   => $controller->listarAlertas(),
        'contagem'  => $controller->contarPorSeveridade(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdminApi();

    $dados = corpoRequisicao();
    if (($dados['acao'] ?? '') !== 'resolver') {
        jsonResponse(['sucesso' => false, 'mensagem' => 'Ação desconhecida.', 'erros' => ['Ação desconhecida.']], 400);
    }

    $alertaId = (int) ($dados['alerta_id'] ?? 0);
    if ($alertaId <= 0) {
        jsonResponse(['sucesso' => false, 'mensagem' => '', 'erros' => ['ID de alerta inválido.']], 422);
    }

    require_once __DIR__ . '/../app/models/Alerta.php';
    try {
        $model = new AlertaModel($pdo);
        $model->marcarResolvido($alertaId);
        registrarLog($pdo, $_SESSION['usuario_id'], 'ALERTA_RESOLVIDO', "Alerta ID $alertaId marcado como resolvido");
        jsonResponse(['sucesso' => true, 'mensagem' => 'Alerta marcado como resolvido.', 'erros' => []]);
    } catch (PDOException $e) {
        jsonResponse(['sucesso' => false, 'mensagem' => '', 'erros' => ['Erro ao atualizar alerta: ' . $e->getMessage()]], 500);
    }
}

jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
