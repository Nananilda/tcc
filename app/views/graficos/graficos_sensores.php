<?php
session_start();
require_once '../../../includes/auth.php';
exigirLogin();

require_once '../../config/conexao.php';

// Sensores disponíveis com unidades
$sensores = [
    'temperatura' => ['label' => 'Temperatura', 'unidade' => '°C'],
    'ruido' => ['label' => 'Ruído', 'unidade' => 'dB'],
    'qualidade_ar' => ['label' => 'Qualidade do Ar', 'unidade' => 'AQI'],
    'umidade' => ['label' => 'Umidade', 'unidade' => '%'],
    'pressao' => ['label' => 'Pressão', 'unidade' => 'hPa'],
    'uv' => ['label' => 'UV', 'unidade' => 'índice'],
];

// Sensor selecionado (padrão: temperatura)
$sensor_sel = $_GET['sensor'] ?? 'temperatura';
if (!array_key_exists($sensor_sel, $sensores)) {
    $sensor_sel = 'temperatura';
}

// Período: últimas 24 h por padrão
$horas = (int) ($_GET['horas'] ?? 24);
if (!in_array($horas, [6, 12, 24, 48, 168])) {
    $horas = 24;
}

// Busca leituras do sensor selecionado
// Tabela: leitura_sensor (sensor_tipo, valor, lido_em)
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
    // Tabela ainda não existe ou erro de consulta: sem dados
    $leituras = [];
}

$labels = array_column($leituras, 'lido_em');
$valores = array_column($leituras, 'valor');
$info = $sensores[$sensor_sel];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico — <?php echo htmlspecialchars($info['label']); ?> — Ákron</title>
    <link rel="icon" type="image/svg+xml" href="../../../public/assets/img/logo.svg">
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/graficos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../../public/assets/js/chart.js"></script>
</head>

<body>

    <div class="topbar">
        <div class="marca">
            <img src="../../../public/assets/img/logo.svg" alt="Ákron" class="marca-logo">
            <div class="marca-texto">
                <span class="marca-nome">ÁKRON</span>
                <span class="marca-secao">Gráficos</span>
            </div>
        </div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
            | Tipo: <?php echo htmlspecialchars($_SESSION['usuario_tipo']); ?>
        </div>
    </div>

    <div class="container">

        <h1>Gráficos de Sensores</h1>

        <!-- Seletor de sensor -->
        <form method="GET" class="graficos-filtros">
            <div class="campo">
                <label for="sensor">Sensor:</label>
                <select id="sensor" name="sensor" onchange="this.form.submit()">
                    <?php foreach ($sensores as $chave => $meta): ?>
                        <option value="<?php echo $chave; ?>" <?php echo $sensor_sel === $chave ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($meta['label']); ?> (<?php echo $meta['unidade']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="horas">Período:</label>
                <select id="horas" name="horas" onchange="this.form.submit()">
                    <?php foreach ([6 => '6 h', 12 => '12 h', 24 => '24 h', 48 => '48 h', 168 => '7 dias'] as $v => $l): ?>
                        <option value="<?php echo $v; ?>" <?php echo $horas === $v ? 'selected' : ''; ?>>
                            <?php echo $l; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <hr>

        <h2>
            <?php echo htmlspecialchars($info['label']); ?> — últimas <?php echo $horas; ?> h
            <?php if (!empty($leituras)): ?>
                <span class="grafico-status-tempo-real">
                    <span class="ponto"></span> tempo real
                </span>
            <?php endif; ?>
        </h2>

        <?php if (empty($leituras)): ?>
            <div class="card">
                <p class="msg-vazio">Nenhuma leitura registrada no banco de dados para este sensor no período selecionado.
                </p>
            </div>
        <?php else: ?>
            <div class="card grafico-wrapper">
                <canvas id="graficoSensor" height="350"></canvas>
            </div>
            <script>
                const graficoSensorAtual = criarGraficoLinha(
                    'graficoSensor',
                    <?php echo json_encode($labels); ?>,
                    <?php echo json_encode(array_map('floatval', $valores)); ?>,
                    '<?php echo addslashes($info['label']); ?> (<?php echo $info['unidade']; ?>)',
                    '<?php echo addslashes($info['label']); ?> — últimas <?php echo $horas; ?> horas'
                );

                // Atualização em tempo real (AJAX) via GraficoController.php
                iniciarAtualizacaoTempoReal(
                    graficoSensorAtual,
                    '<?php echo $sensor_sel; ?>',
                    <?php echo $horas; ?>
                );
            </script>
            <p class="grafico-total-leituras">Total de leituras: <?php echo count($leituras); ?></p>
        <?php endif; ?>

        <div class="nav-rodape">
            <a href="alerta.php">Ver alertas →</a>
            <a href="../dashboard/painel.php">← Voltar ao painel</a>
        </div>

    </div>

</body>

</html>