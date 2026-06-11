<?php
session_start();
require_once '../../../includes/auth.php';
exigirLogin();

require_once '../../config/conexao.php';

// ── Filtros ──────────────────────────────────────────────────────────────────
$sensor_tipos = ['temperatura', 'ruido', 'qualidade_ar', 'umidade', 'pressao', 'uv'];

$filtro_sensor    = $_GET['sensor']     ?? '';
$filtro_data_ini  = $_GET['data_ini']   ?? date('Y-m-d', strtotime('-7 days'));
$filtro_data_fim  = $_GET['data_fim']   ?? date('Y-m-d');
$filtro_min       = $_GET['val_min']    ?? '';
$filtro_max       = $_GET['val_max']    ?? '';

// Valida datas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_ini)) $filtro_data_ini = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_fim)) $filtro_data_fim = date('Y-m-d');

// ── Query dinâmica ───────────────────────────────────────────────────────────
$params = [
    ':ini' => $filtro_data_ini . ' 00:00:00',
    ':fim' => $filtro_data_fim . ' 23:59:59',
];
$where = "WHERE lido_em BETWEEN :ini AND :fim";

if ($filtro_sensor && in_array($filtro_sensor, $sensor_tipos)) {
    $where .= " AND sensor_tipo = :tipo";
    $params[':tipo'] = $filtro_sensor;
}
if ($filtro_min !== '' && is_numeric($filtro_min)) {
    $where .= " AND valor >= :vmin";
    $params[':vmin'] = (float)$filtro_min;
}
if ($filtro_max !== '' && is_numeric($filtro_max)) {
    $where .= " AND valor <= :vmax";
    $params[':vmax'] = (float)$filtro_max;
}

$leituras = [];
$resumo   = [];
try {
    // Leituras paginadas (últimas 200 para exibição)
    $stmt = $pdo->prepare("
        SELECT sensor_tipo, valor, lido_em
        FROM leitura_sensor
        $where
        ORDER BY lido_em DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $leituras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resumo estatístico por tipo de sensor
    $stmt2 = $pdo->prepare("
        SELECT sensor_tipo,
               COUNT(*)    AS total,
               ROUND(AVG(valor),2) AS media,
               ROUND(MIN(valor),2) AS minimo,
               ROUND(MAX(valor),2) AS maximo
        FROM leitura_sensor
        $where
        GROUP BY sensor_tipo
        ORDER BY sensor_tipo
    ");
    $stmt2->execute($params);
    $resumo = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $leituras = [];
    $resumo   = [];
}

$labels_pt = [
    'temperatura'  => 'Temperatura (°C)',
    'ruido'        => 'Ruído (dB)',
    'qualidade_ar' => 'Qualidade do Ar (AQI)',
    'umidade'      => 'Umidade (%)',
    'pressao'      => 'Pressão (hPa)',
    'uv'           => 'UV (índice)',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Qualidade</title>
</head>
<body>

<h1>Relatório de Qualidade Ambiental</h1>

<p>Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
   | Tipo: <?php echo htmlspecialchars($_SESSION['usuario_tipo']); ?></p>

<!-- ── Filtros ────────────────────────────────────────────────────────── -->
<form method="GET">
    <fieldset>
        <legend>Filtros</legend>

        <label for="sensor">Sensor:</label>
        <select id="sensor" name="sensor">
            <option value="">— Todos —</option>
            <?php foreach ($sensor_tipos as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $filtro_sensor === $s ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($labels_pt[$s] ?? $s); ?>
                </option>
            <?php endforeach; ?>
        </select>

        &nbsp;
        <label for="data_ini">De:</label>
        <input type="date" id="data_ini" name="data_ini" value="<?php echo htmlspecialchars($filtro_data_ini); ?>">

        <label for="data_fim">Até:</label>
        <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">

        &nbsp;
        <label for="val_min">Valor mín.:</label>
        <input type="number" id="val_min" name="val_min" step="any" style="width:80px"
               value="<?php echo htmlspecialchars($filtro_min); ?>">

        <label for="val_max">Valor máx.:</label>
        <input type="number" id="val_max" name="val_max" step="any" style="width:80px"
               value="<?php echo htmlspecialchars($filtro_max); ?>">

        &nbsp;
        <button type="submit">Filtrar</button>
        <a href="relatorio_qualidade.php">Limpar filtros</a>
    </fieldset>
</form>

<hr>

<!-- ── Resumo estatístico ─────────────────────────────────────────────── -->
<?php if (!empty($resumo)): ?>
<h2>Resumo do período</h2>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>Sensor</th>
            <th>Leituras</th>
            <th>Média</th>
            <th>Mínimo</th>
            <th>Máximo</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($resumo as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($labels_pt[$r['sensor_tipo']] ?? $r['sensor_tipo']); ?></td>
                <td><?php echo (int)$r['total']; ?></td>
                <td><?php echo $r['media']; ?></td>
                <td><?php echo $r['minimo']; ?></td>
                <td><?php echo $r['maximo']; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Leituras detalhadas ────────────────────────────────────────────── -->
<h2>Leituras detalhadas <?php echo count($leituras) >= 200 ? '(últimas 200)' : '(' . count($leituras) . ' registros)'; ?></h2>

<?php if (empty($leituras)): ?>
    <p><em>Nenhuma leitura encontrada com os filtros aplicados.</em></p>
<?php else: ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Sensor</th>
                <th>Valor</th>
                <th>Data/Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leituras as $l): ?>
                <tr>
                    <td><?php echo htmlspecialchars($labels_pt[$l['sensor_tipo']] ?? $l['sensor_tipo']); ?></td>
                    <td><?php echo htmlspecialchars($l['valor']); ?></td>
                    <td><?php echo htmlspecialchars($l['lido_em']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<br>
<a href="/tcc/app/views/dashboard/painel.php">← Voltar ao painel</a>

</body>
</html>