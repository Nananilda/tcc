<?php
/**
 * GET api/relatorio.php?sensor=&data_ini=YYYY-MM-DD&data_fim=YYYY-MM-DD&val_min=&val_max=
 * Retorna: { sucesso, filtros, leituras: [...], resumo: [...] }
 *   leituras → até 200 leituras (mais recentes primeiro)
 *   resumo   → total/média/mínimo/máximo por tipo de sensor (sobre TODAS as leituras do filtro)
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();

require_once __DIR__ . '/../app/controllers/RelatorioController.php';

$resultado = (new RelatorioController($pdo))->consultar($_GET);

if ($resultado['erro'] !== '') {
    jsonResponse(['sucesso' => false, 'mensagem' => $resultado['erro']], 500);
}

// Tipos corretos no JSON (valores numéricos como número)
$leituras = array_map(fn($l) => [
    'sensor_tipo' => $l['sensor_tipo'],
    'sensor_nome' => $l['sensor_nome'],
    'valor' => (float) $l['valor'],
    'lido_em' => $l['lido_em'],
], $resultado['leituras']);

$resumo = array_map(fn($r) => [
    'sensor_tipo' => $r['sensor_tipo'],
    'total' => (int) $r['total'],
    'media' => (float) $r['media'],
    'minimo' => (float) $r['minimo'],
    'maximo' => (float) $r['maximo'],
], $resultado['resumo']);

jsonResponse([
    'sucesso' => true,
    'filtros' => $resultado['filtros'],
    'leituras' => $leituras,
    'resumo' => $resumo,
]);
