<?php
/**
 * app/views/graficos/graficos_sensores.php — Gráficos de Sensores.
 *
 * Mostra a série de leituras de um tipo de sensor (temperatura, ruído...) em
 * um gráfico de linha, na janela de tempo escolhida (6 h a 7 dias). Depois de
 * carregada, a página se atualiza sozinha a cada 15 s (ver graficos.js e
 * routes/grafico_dados.php).
 *
 * Parâmetros GET: ?sensor=temperatura&horas=24 (valores inválidos viram o padrão).
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/GraficoController.php';

// Consulta já validada pelo controller (sensor e janela de horas corrigidos)
$resultado = (new GraficoController($pdo))->serie($_GET);
$sensorSel = $resultado['sensor'];
$horas = $resultado['horas'];
$pontos = $resultado['pontos'];

$labels = array_column($pontos, 'lido_em');
$valores = array_column($pontos, 'valor');
$rotuloSensor = SensorModel::rotulo($sensorSel);
$rotuloComUnidade = SensorModel::rotuloComUnidade($sensorSel);
$rotuloJanela = LeituraModel::rotuloJanela($horas); // ex.: 'últimas 24 h' ou 'últimos 7 dias'

$tituloPagina = 'Gráfico — ' . $rotuloSensor;
$secaoTopbar = 'Gráficos';
$cssPagina = ['graficos.css'];
$scriptsHead = ['vendor/chart.umd.js', 'graficos.js']; // Chart.js + nossos utilitários
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Gráficos de Sensores</h1>

<!-- Seletores: ao mudar qualquer um, a página recarrega com os novos filtros -->
<form method="GET" class="graficos-filtros">
    <div class="campo">
        <label for="sensor">Sensor:</label>
        <select id="sensor" name="sensor" onchange="this.form.submit()">
            <?php foreach (array_keys(SensorModel::TIPOS) as $tipo): ?>
                <option value="<?php echo escapar($tipo); ?>" <?php echo $sensorSel === $tipo ? 'selected' : ''; ?>>
                    <?php echo escapar(SensorModel::rotuloComUnidade($tipo)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="campo">
        <label for="horas">Período:</label>
        <select id="horas" name="horas" onchange="this.form.submit()">
            <?php foreach (LeituraModel::ROTULOS_PERIODO as $valor => $rotulo): ?>
                <option value="<?php echo $valor; ?>" <?php echo $horas === $valor ? 'selected' : ''; ?>>
                    <?php echo escapar($rotulo); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<hr>

<h2>
    <?php echo escapar($rotuloSensor . ' — ' . $rotuloJanela); ?>
    <?php if (!empty($pontos)): ?>
        <span class="grafico-status-tempo-real">
            <span class="ponto"></span> tempo real
        </span>
    <?php endif; ?>
</h2>

<?php if ($resultado['erro'] !== ''): ?>
    <div class="msg-erro" role="alert"><?php echo escapar($resultado['erro']); ?></div>
<?php elseif (empty($pontos)): ?>
    <div class="card">
        <p class="msg-vazio">Nenhuma leitura registrada no banco de dados para este sensor no período selecionado.</p>
    </div>
<?php else: ?>
    <div class="card grafico-wrapper">
        <div class="grafico-area"><canvas id="graficoSensor"></canvas></div>
    </div>
    <script>
        const graficoSensorAtual = criarGraficoLinha(
            'graficoSensor',
            <?php echo jsonParaScript($labels); ?>,
            <?php echo jsonParaScript($valores); ?>,
            <?php echo jsonParaScript($rotuloComUnidade); ?>,
            <?php echo jsonParaScript($rotuloSensor . ' — ' . $rotuloJanela); ?>
        );

        // Atualização em tempo real (AJAX) via routes/grafico_dados.php
        iniciarAtualizacaoTempoReal(
            graficoSensorAtual,
            <?php echo jsonParaScript(urlApp('routes/grafico_dados.php')); ?>,
            <?php echo jsonParaScript($sensorSel); ?>,
            <?php echo (int) $horas; ?>
        );
    </script>
    <p class="grafico-total-leituras">
        Total de leituras: <span id="graficoTotalLeituras"><?php echo (int) $resultado['total']; ?></span>
    </p>
<?php endif; ?>

<div class="nav-rodape">
    <a href="alerta.php">Ver alertas →</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
