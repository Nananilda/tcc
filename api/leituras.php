<?php
/**
 * GET api/leituras.php?sensor=temperatura&horas=24
 * Usado pela tela de Gráficos de Sensores (atualização periódica).
 *
 * `sensor` → tipo (temperatura, ruido, qualidade_ar, umidade, pressao, uv)
 * `horas`  → janela: 6, 12, 24, 48 ou 168
 * Retorna: { sucesso, sensor, horas, labels: [...], valores: [...], total }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();

require_once __DIR__ . '/../app/controllers/GraficoController.php';

$resultado = (new GraficoController($pdo))->serie($_GET);

if ($resultado['erro'] !== '') {
    jsonResponse(['sucesso' => false, 'mensagem' => $resultado['erro']], 500);
}

jsonResponse([
    'sucesso' => true,
    'sensor' => $resultado['sensor'],
    'horas' => $resultado['horas'],
    'labels' => array_column($resultado['pontos'], 'lido_em'),
    'valores' => array_column($resultado['pontos'], 'valor'),
    'total' => $resultado['total'],
]);
