<?php
require_once '../../includes/auth.php';
exigirLogin(); // já chama session_start() internamente

require_once '../config/conexao.php';
require_once '../models/Relatorio.php';

// ── Instancia o model ────────────────────────────────────────────────────────
$relatorioModel = new Relatorio($pdo);

// ── Lê e sanitiza os filtros da requisição ───────────────────────────────────
$filtro_sensor   = $_GET['sensor']   ?? '';
$filtro_data_ini = $_GET['data_ini'] ?? date('Y-m-d', strtotime('-7 days'));
$filtro_data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$filtro_min      = $_GET['val_min']  ?? '';
$filtro_max      = $_GET['val_max']  ?? '';

// Valida formato das datas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_ini))
    $filtro_data_ini = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_fim))
    $filtro_data_fim = date('Y-m-d');

// ── Monta filtros via model ──────────────────────────────────────────────────
$filtros = $relatorioModel->montarFiltros(
    $filtro_data_ini,
    $filtro_data_fim,
    $filtro_sensor,
    $filtro_min,
    $filtro_max
);

// ── Busca os dados ───────────────────────────────────────────────────────────
$leituras = [];
$resumo   = [];

try {
    $leituras = $relatorioModel->buscarLeituras($filtros['where'], $filtros['params']);
    $resumo   = $relatorioModel->buscarResumo($filtros['where'], $filtros['params']);
} catch (PDOException $e) {
    $leituras = [];
    $resumo   = [];
}

// ── Metadados para a view ────────────────────────────────────────────────────
$sensor_tipos = $relatorioModel->getSensorTipos();
$labels_pt    = $relatorioModel->getLabels();

// ── Carrega a view ───────────────────────────────────────────────────────────
require_once '../views/relatorios/relatorio_qualidade.php';
