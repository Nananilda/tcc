<?php
/**
 * GET api/relatorio.php?sensor=&data_ini=YYYY-MM-DD&data_fim=YYYY-MM-DD&val_min=&val_max=
 * Retorna: { sucesso, leituras: [...], resumo: [...] }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();
require_once __DIR__ . '/../app/models/Relatorio.php';

$relatorioModel = new Relatorio($pdo);

$filtro_sensor   = $_GET['sensor']   ?? '';
$filtro_data_ini = $_GET['data_ini'] ?? date('Y-m-d', strtotime('-7 days'));
$filtro_data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$filtro_min      = $_GET['val_min']  ?? '';
$filtro_max      = $_GET['val_max']  ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_ini)) {
    $filtro_data_ini = date('Y-m-d', strtotime('-7 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_fim)) {
    $filtro_data_fim = date('Y-m-d');
}

$filtros = $relatorioModel->montarFiltros(
    $filtro_data_ini,
    $filtro_data_fim,
    $filtro_sensor,
    $filtro_min,
    $filtro_max
);

$leituras = [];
$resumo   = [];
try {
    $leituras = $relatorioModel->buscarLeituras($filtros['where'], $filtros['params']);
    $resumo   = $relatorioModel->buscarResumo($filtros['where'], $filtros['params']);
} catch (PDOException $e) {
    $leituras = [];
    $resumo   = [];
}

jsonResponse([
    'sucesso'  => true,
    'leituras' => $leituras,
    'resumo'   => $resumo,
]);
