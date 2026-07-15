<?php
/**
 * GraficoController.php
 * Endpoint JSON usado pelo JavaScript (public/assets/js/chart.js) para
 * a atualização em tempo real (AJAX) do gráfico em graficos_sensores.php.
 *
 * Uso: GET GraficoController.php?sensor=temperatura&horas=24
 * Retorna: { "labels": [...], "valores": [...] }
 *
 * Segue a mesma lógica de consulta já usada em
 * app/views/graficos/graficos_sensores.php (mesma tabela, mesmos filtros),
 * apenas devolvendo os dados em JSON em vez de montar o Chart.js diretamente.
 */

session_start();
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../models/DadosExemplo.php';

header('Content-Type: application/json; charset=utf-8');

// Sensores válidos (mesma lista de graficos_sensores.php)
$sensores_validos = ['temperatura', 'ruido', 'qualidade_ar', 'umidade', 'pressao', 'uv'];

$sensor_sel = $_GET['sensor'] ?? 'temperatura';
if (!in_array($sensor_sel, $sensores_validos, true)) {
    $sensor_sel = 'temperatura';
}

$horas = (int) ($_GET['horas'] ?? 24);
if (!in_array($horas, [6, 12, 24, 48, 168], true)) {
    $horas = 24;
}

$leituras = [];
try {
    $stmt = $pdo->prepare("
        SELECT valor, lido_em
        FROM leitura_sensor
        WHERE sensor_tipo = :tipo
          AND lido_em >= DATE_SUB(NOW(), INTERVAL :horas HOUR)
        ORDER BY lido_em ASC
        LIMIT 500
    ");
    $stmt->execute([':tipo' => $sensor_sel, ':horas' => $horas]);
    $leituras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $leituras = [];
}

// Base de dados de exemplo enquanto o banco real não está populado,
// para o gráfico continuar funcional durante a atualização em tempo real.
if (empty($leituras)) {
    $leituras = gerarLeiturasExemplo($sensor_sel, $horas);
}

echo json_encode([
    'sensor'  => $sensor_sel,
    'horas'   => $horas,
    'labels'  => array_column($leituras, 'lido_em'),
    'valores' => array_map('floatval', array_column($leituras, 'valor')),
]);
