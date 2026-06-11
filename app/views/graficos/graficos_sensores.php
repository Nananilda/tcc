<?php
session_start();
require_once '../../../includes/auth.php';
exigirLogin();

require_once '../../config/conexao.php';

// Sensores disponíveis com unidades
$sensores = [
    'temperatura'      => ['label' => 'Temperatura',      'unidade' => '°C'],
    'ruido'            => ['label' => 'Ruído',             'unidade' => 'dB'],
    'qualidade_ar'     => ['label' => 'Qualidade do Ar',   'unidade' => 'AQI'],
    'umidade'          => ['label' => 'Umidade',           'unidade' => '%'],
    'pressao'          => ['label' => 'Pressão',           'unidade' => 'hPa'],
    'uv'               => ['label' => 'UV',                'unidade' => 'índice'],
];

// Sensor selecionado (padrão: temperatura)
$sensor_sel = $_GET['sensor'] ?? 'temperatura';
if (!array_key_exists($sensor_sel, $sensores)) {
    $sensor_sel = 'temperatura';
}

// Período: últimas 24 h por padrão
$horas = (int)($_GET['horas'] ?? 24);
if (!in_array($horas, [6, 12, 24, 48, 168])) {
    $horas = 24;
}

// Busca leituras do sensor selecionado
// Tabela esperada: leitura_sensor (sensor_tipo, valor, lido_em)
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
    // Tabela ainda não existe: sem dados
    $leituras = [];
}

$labels  = array_column($leituras, 'lido_em');
$valores = array_column($leituras, 'valor');
$info    = $sensores[$sensor_sel];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gráfico — <?php echo htmlspecialchars($info['label']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<h1>Gráficos de Sensores</h1>

<p>Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
   | Tipo: <?php echo htmlspecialchars($_SESSION['usuario_tipo']); ?></p>

<!-- Seletor de sensor -->
<form method="GET">
    <label for="sensor">Sensor:</label>
    <select id="sensor" name="sensor" onchange="this.form.submit()">
        <?php foreach ($sensores as $chave => $meta): ?>
            <option value="<?php echo $chave; ?>" <?php echo $sensor_sel === $chave ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($meta['label']); ?> (<?php echo $meta['unidade']; ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label for="horas">Período:</label>
    <select id="horas" name="horas" onchange="this.form.submit()">
        <?php foreach ([6 => '6 h', 12 => '12 h', 24 => '24 h', 48 => '48 h', 168 => '7 dias'] as $v => $l): ?>
            <option value="<?php echo $v; ?>" <?php echo $horas === $v ? 'selected' : ''; ?>>
                <?php echo $l; ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<hr>

<h2><?php echo htmlspecialchars($info['label']); ?> — últimas <?php echo $horas; ?> h</h2>

<?php if (empty($leituras)): ?>
    <p><em>Nenhuma leitura encontrada para este sensor no período selecionado.</em></p>
<?php else: ?>
    <canvas id="graficoSensor" width="900" height="350"></canvas>
    <script>
        const ctx = document.getElementById('graficoSensor').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: '<?php echo addslashes($info['label']); ?> (<?php echo $info['unidade']; ?>)',
                    data: <?php echo json_encode(array_map('floatval', $valores)); ?>,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: <?php echo count($leituras) > 100 ? 0 : 3; ?>
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: {
                        display: true,
                        text: '<?php echo addslashes($info["label"]); ?> — últimas <?php echo $horas; ?> horas'
                    }
                },
                scales: {
                    x: {
                        ticks: { maxTicksLimit: 12, maxRotation: 45 }
                    },
                    y: {
                        title: {
                            display: true,
                            text: '<?php echo addslashes($info["unidade"]); ?>'
                        }
                    }
                }
            }
        });
    </script>
    <p>Total de leituras: <?php echo count($leituras); ?></p>
<?php endif; ?>

<br>
<a href="/tcc/app/views/dashboard/painel.php">← Voltar ao painel</a>

</body>
</html>