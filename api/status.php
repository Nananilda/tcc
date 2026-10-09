<?php
/**
 * GET api/status.php
 * Verificação de saúde (não exige login). O app a usa para testar se o
 * endereço da API está correto e se o servidor alcança o banco de dados.
 *
 * Se o banco estiver fora do ar, o próprio _bootstrap.php já responde 503.
 * Retorna: { sucesso, sistema, versao, banco: "ok" }
 */

require_once __DIR__ . '/_bootstrap.php';

// Consulta mínima só para confirmar que o banco responde de verdade
$pdo->query('SELECT 1');

jsonResponse([
    'sucesso' => true,
    'sistema' => APP_NOME,
    'descricao' => APP_DESCRICAO,
    'versao' => APP_VERSAO,
    'banco' => 'ok',
]);
