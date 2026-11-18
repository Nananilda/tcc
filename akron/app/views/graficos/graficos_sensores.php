<?php
require_once __DIR__.'/../../../includes/auth.php';exigirLogin();
require_once __DIR__.'/../../controllers/GraficoController.php';
$r=(new GraficoController($pdo))->conjunto($_GET);
$filtros=array_intersect_key($_GET,array_flip(['estoque_id','sensor_id','sensor','horas','data_ini','data_fim']));$filtroJanela=true;
/* Retorno: origem informada; no acesso direto, o sensor ou o estoque filtrado (contexto preservado) ou o painel. */
$sensorCtx=(int)($_GET['sensor_id']??0);$estoqueCtx=(int)($_GET['estoque_id']??0);
$padraoVoltar=$sensorCtx?'app/views/sensores/detalhes.php?id='.$sensorCtx:($estoqueCtx?'app/views/estoques/detalhes.php?id='.$estoqueCtx:'app/views/dashboard/painel.php');
$tituloPagina='Monitoramento';$cssPagina=['graficos.css','monitoramento.css'];$scriptsHead=['vendor/chart.umd.js','graficos.js','monitoramento.js'];
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Monitoramento</h1><p>Acompanhe os valores atuais e consulte o histórico por tipo de medição.</p></div><div class="actions"><span class="badge badge-info">Atualização a cada 15 s</span><?= linkVoltar($padraoVoltar) ?></div></header>
<?php require __DIR__.'/../partials/filtros_monitoramento.php'; ?>
<p id="monitor-status" role="status" aria-live="polite"><?= escapar($r['erro']) ?></p>
<section class="card"><h2>Leituras atuais</h2><p>Último valor recebido de cada sensor selecionado. O período filtra o histórico; leituras atuais preservam a data real da última medição.</p><div id="monitor-atuais" class="sensores-grid"></div></section>
<div id="monitor-controles" class="monitor-controles"></div>
<div id="monitor-graficos" class="graficos-recolhiveis"></div><p id="monitor-total" role="status"></p>
<section class="card"><h2>Histórico completo</h2><p>Consulte cada medição e exporte os registros no relatório. Cada variável tem gráfico e unidade próprios; escolha acima quais tipos exibir e recolha os que não precisa ver.</p><a class="btn-secundario" href="<?= escapar(urlComOrigem('app/views/relatorios/relatorio_qualidade.php',$filtros)) ?>">Consultar histórico e exportar</a></section>
<script>iniciarMonitoramento(<?= jsonParaScript($r) ?>,<?= jsonParaScript(urlApp('routes/grafico_dados.php').'?'.http_build_query($filtros+['todos'=>1])) ?>);</script>
<?php require __DIR__.'/../partials/rodape.php'; ?>
