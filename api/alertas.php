<?php
/**
 * api/alertas.php
 *
 * GET  -> lista alertas recentes + contagem de pendentes por severidade.
 * POST -> (SOMENTE ADMIN)
 *         acao=resolver  Body: { "acao": "resolver", "alerta_id": N }
 *         acao=criar     Body: { "acao": "criar", "sensor_id": N,
 *                                "severidade": "info|atencao|critico",
 *                                "mensagem": "texto (3 a 200 caracteres)",
 *                                "valor": 36.5 }   ← valor é opcional
 *
 * Os alertas também nascem sozinhos quando uma leitura passa de um limite —
 * veja api/leituras.php (POST) e AlertaModel::LIMITES.
 *
 * Retornos:
 *   GET  → { sucesso, alertas: [{id, sensor_id, sensor_tipo, sensor_nome, severidade,
 *            mensagem, valor, resolvido, criado_em}], contagem: {critico, atencao, info},
 *            limites: {tipo: {min: {atencao, critico}, max: {atencao, critico}}} }
 *          (`limites` = faixas dos alertas automáticos, de AlertaModel::LIMITES)
 *   POST → { sucesso, mensagem, erros }   (criar também devolve `id`, o código do novo alerta)
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();

require_once __DIR__ . '/../app/controllers/AlertaController.php';

$controller = new AlertaController($pdo, ehAdmin());

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Normaliza os tipos para o JSON (números como número, resolvido como booleano)
    $alertas = array_map(fn($a) => [
        'id' => (int) $a['id'],
        'sensor_id' => (int) $a['sensor_id'],
        'sensor_tipo' => $a['sensor_tipo'],
        'sensor_nome' => $a['sensor_nome'],
        'severidade' => $a['severidade'],
        'mensagem' => $a['mensagem'],
        'valor' => $a['valor'] === null ? null : (float) $a['valor'],
        'resolvido' => (bool) $a['resolvido'],
        'criado_em' => $a['criado_em'],
    ], $controller->listarAlertas());

    jsonResponse([
        'sucesso' => true,
        'alertas' => $alertas,
        'contagem' => $controller->contarPorSeveridade(),
        'limites' => AlertaModel::LIMITES, // o app mostra a tabela de limites sem duplicar os números
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdminApi();

    $dados = corpoRequisicao();
    respostaDoControlador($controller->executar($dados['acao'] ?? '', $dados));
}

jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
