<?php
/**
 * api/sensores.php
 *
 * GET  -> lista todos os sensores (qualquer usuário logado — usado tanto
 *         pela consulta somente-leitura quanto pela gestão).
 * POST -> acao=cadastrar | acao=toggle_status (SOMENTE ADMIN).
 *         Usuário comum não tem acesso a estas ações: a Gestão de Sensores
 *         é restrita a administradores, tanto aqui quanto na tela do app.
 *
 * Body POST (JSON):
 *   cadastrar:      { "acao": "cadastrar", "nome", "tipo", "localizacao", "status" }
 *   toggle_status:  { "acao": "toggle_status", "sensor_id", "novo_status" }
 *
 * Retornos:
 *   GET  → { sucesso, sensores: [{id, nome, tipo, localizacao, status, criado_em}], erros }
 *   POST → { sucesso, mensagem, erros }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();

require_once __DIR__ . '/../app/controllers/SensorController.php';

$controller = new SensorController($pdo, ehAdmin());

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $erros = [];
    $sensores = $controller->listarSensores($erros);

    // Tipos numéricos corretos no JSON (o PDO devolve o id como texto em alguns drivers)
    $sensores = array_map(fn($s) => ['id' => (int) $s['id']] + $s, $sensores);

    jsonResponse(['sucesso' => empty($erros), 'sensores' => $sensores, 'erros' => $erros], empty($erros) ? 200 : 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reforço explícito: gestão de sensores (cadastro/alteração de status)
    // é exclusiva de administradores — usuário comum é bloqueado aqui.
    exigirAdminApi();

    $dados = corpoRequisicao();
    respostaDoControlador($controller->executar($dados['acao'] ?? '', $dados));
}

jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
