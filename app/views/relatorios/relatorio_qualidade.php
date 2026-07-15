<?php
// Segurança: bloqueia acesso direto à view
if (!isset($leituras, $resumo, $sensor_tipos, $labels_pt)) {
    header('Location: /tcc/app/views/dashboard/painel.php');
    exit;
}

// Fallback para variáveis de filtro
$filtro_sensor   = $filtro_sensor   ?? '';
$filtro_data_ini = $filtro_data_ini ?? date('Y-m-d', strtotime('-7 days'));
$filtro_data_fim = $filtro_data_fim ?? date('Y-m-d');
$filtro_min      = $filtro_min      ?? '';
$filtro_max      = $filtro_max      ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Qualidade</title>
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/relatorios.css">
</head>

<body>

    <div class="topbar">
        <div class="marca">IndustrialOS — Relatórios</div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? ''); ?></strong>
            | Tipo: <?php echo htmlspecialchars($_SESSION['usuario_tipo'] ?? ''); ?>
        </div>
    </div>

    <div class="container">

    <h1>Relatório de Qualidade Ambiental</h1>

    <!-- ── Filtros ────────────────────────────────────────────────────────── -->
    <form method="GET">
        <fieldset>
            <legend>Filtros</legend>

            <label for="sensor">Sensor:</label>
            <select id="sensor" name="sensor">
                <option value="">— Todos —</option>
                <?php foreach ($sensor_tipos as $s): ?>
                    <option value="<?php echo $s; ?>"
                        <?php echo $filtro_sensor === $s ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($labels_pt[$s] ?? $s); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            &nbsp;
            <label for="data_ini">De:</label>
            <input type="date" id="data_ini" name="data_ini"
                value="<?php echo htmlspecialchars($filtro_data_ini); ?>">

            <label for="data_fim">Até:</label>
            <input type="date" id="data_fim" name="data_fim"
                value="<?php echo htmlspecialchars($filtro_data_fim); ?>">

            &nbsp;
            <label for="val_min">Valor mín.:</label>
            <input type="number" id="val_min" name="val_min" step="any" style="width:80px"
                value="<?php echo htmlspecialchars($filtro_min); ?>">

            <label for="val_max">Valor máx.:</label>
            <input type="number" id="val_max" name="val_max" step="any" style="width:80px"
                value="<?php echo htmlspecialchars($filtro_max); ?>">

            &nbsp;
            <button type="submit">Filtrar</button>
            <a href="/tcc/app/controllers/RelatorioController.php">Limpar filtros</a>
        </fieldset>
    </form>

    <hr>

    <!-- ── Resumo estatístico ─────────────────────────────────────────────── -->
    <?php if (!empty($resumo)): ?>
        <h2>Resumo do período</h2>
        <table class="relatorio-resumo-tabela">
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
                        <td><?php echo (int) $r['total']; ?></td>
                        <td><?php echo $r['media']; ?></td>
                        <td><?php echo $r['minimo']; ?></td>
                        <td><?php echo $r['maximo']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ── Leituras detalhadas ────────────────────────────────────────────── -->
    <h2>Leituras detalhadas
        <span class="relatorio-contagem"><?php echo count($leituras) >= 200
            ? '(últimas 200)'
            : '(' . count($leituras) . ' registros)'; ?></span>
    </h2>

    <?php if (empty($leituras)): ?>
        <p><em>Nenhuma leitura encontrada com os filtros aplicados.</em></p>
    <?php else: ?>
        <div class="relatorio-tabela-detalhe">
        <table>
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
        </div>
    <?php endif; ?>

    <div class="nav-rodape">
        <a href="../dashboard/painel.php">← Voltar ao painel</a>
    </div>

    </div>

</body>

</html>
