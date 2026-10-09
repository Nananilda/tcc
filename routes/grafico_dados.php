<?php
/**
 * routes/grafico_dados.php
 * Endpoint JSON da atualização em tempo real (AJAX) dos gráficos de
 * app/views/graficos/graficos_sensores.php — chamado de tempos em tempos por
 * public/assets/js/graficos.js.
 *
 * Uso:     GET routes/grafico_dados.php?sensor=temperatura&horas=24
 * Retorna: { "sensor", "horas", "labels": [...], "valores": [...], "total" }
 * Sessão expirada: HTTP 401 com { "sucesso": false, "mensagem": "..." }
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Para AJAX não se redireciona: responde 401 para o JavaScript decidir o que fazer
if (verificarSessao() !== null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../app/config/conexao.php';
require_once __DIR__ . '/../app/controllers/GraficoController.php';

echo json_encode(
    (new GraficoController($pdo))->serieJson($_GET),
    JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
);
