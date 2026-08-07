<?php
/**
 * GET api/leituras.php?sensor=temperatura&horas=24
 * Usado pela tela de Gráficos de Sensores (atualização periódica).
 * Retorna: { sucesso, labels: [...], valores: [...] }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();
require_once __DIR__ . '/../app/models/DadosExemplo.php';

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
    $stmt->bindValue(':tipo', $sensor_sel);
    $stmt->bindValue(':horas', $horas, PDO::PARAM_INT);
    $stmt->execute();
    $leituras = $stmt->fetchAll();
} catch (PDOException $e) {
    $leituras = [];
}

if (empty($leituras)) {
    $leituras = gerarLeiturasExemplo($sensor_sel, $horas);
}

jsonResponse([
    'sucesso' => true,
    'sensor'  => $sensor_sel,
    'horas'   => $horas,
    'labels'  => array_column($leituras, 'lido_em'),
    'valores' => array_map('floatval', array_column($leituras, 'valor')),
]);
