<?php
/**
 * api/leituras.php
 *
 * GET  api/leituras.php?sensor=temperatura&horas=24
 *      Usado pela tela de Gráficos de Sensores (atualização periódica).
 *      `sensor` → tipo (temperatura, ruido, qualidade_ar, umidade, pressao, uv)
 *      `horas`  → janela: 6, 12, 24, 48 ou 168
 *      Retorna: { sucesso, sensor, horas, labels: [...], valores: [...], total }
 *
 * POST api/leituras.php   Body: { "sensor_id": N, "valor": 36.5 }
 *      Registra uma leitura de um sensor ATIVO. Se o valor passar de um limite
 *      do tipo do sensor (AlertaModel::LIMITES), um alerta é criado
 *      automaticamente (sem duplicar um alerta igual ainda pendente).
 *      Quem pode enviar:
 *        - um sensor/gateway, com o cabeçalho  X-Chave-Sensor: <chave>
 *          (chave definida em AKRON_CHAVE_SENSORES — veja app/config/app.php);
 *        - ou um administrador logado (Authorization: Bearer <token>).
 *      Retorna: { sucesso, mensagem, erros, leitura_id,
 *                 alerta: null | { id, severidade, mensagem, novo } }
 */

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Um dispositivo se identifica pela chave; do contrário, só admin logado
    $chave = chaveDosSensores();
    $enviada = (string) ($_SERVER['HTTP_X_CHAVE_SENSOR'] ?? '');
    $ehSensor = $chave !== '' && $enviada !== '' && hash_equals($chave, $enviada);

    if (!$ehSensor) {
        exigirAdminApi();
    }

    require_once __DIR__ . '/../app/controllers/LeituraController.php';
    $usuarioId = $ehSensor ? null : (int) $_SESSION['usuario_id'];
    respostaDoControlador((new LeituraController($pdo))->registrar(corpoRequisicao(), $usuarioId));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

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
