<?php
require_once __DIR__.'/../../../includes/auth.php'; exigirLogin();
require_once __DIR__.'/../../config/conexao.php';
require_once __DIR__.'/../../controllers/DashboardController.php';
require_once __DIR__.'/../../controllers/GraficoController.php';
require_once __DIR__.'/../../models/EstoqueModel.php';
$resumo=(new DashboardController($pdo))->obterResumo($_GET);
$estoqueId=max(0,(int)($_GET['estoque_id']??0));$estoques=(new EstoqueModel($pdo))->listarTodos();
$series=[];foreach(array_keys(SensorModel::TIPOS) as $tipo) $series[$tipo]=(new GraficoController($pdo))->serieJson(['estoque_id'=>$estoqueId,'sensor'=>$tipo,'horas'=>24]);
$tituloPagina='Painel';$cssPagina=['painel.css','graficos.css','monitoramento.css'];$scriptsHead=['vendor/chart.umd.js','graficos.js','monitoramento.js'];
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Painel</h1><p>Condições ambientais dos estoques, comunicação e alertas que precisam de atenção.</p></div><form method="get"><label for="painel-estoque">Estoque</label><select name="estoque_id" id="painel-estoque"><option value="0">Todos os autorizados</option><?php foreach($estoques as $e): ?><option value="<?= (int)$e['id'] ?>" <?= $estoqueId==(int)$e['id']?'selected':'' ?>><?= escapar($e['nome']) ?></option><?php endforeach ?></select><button type="submit">Aplicar</button></form></header>
<p id="painel-status" role="status" aria-live="polite"><?= escapar($resumo['erro']) ?></p>
<div class="painel-stats"><?php foreach(['estoques_total'=>'Estoques','sensores_online'=>'Sensores online','alertas_pendentes'=>'Alertas ativos','estoques_irregulares'=>'Estoques com irregularidades'] as $k=>$label): ?><div class="stat-card"><div class="stat-valor" id="stat-<?= $k ?>"><?= (int)$resumo[$k] ?></div><div class="stat-label"><?= $label ?></div></div><?php endforeach ?></div>
<p>Última leitura recebida: <strong id="painel-ultima"><?= escapar($resumo['ultima_atualizacao']?dataBr($resumo['ultima_atualizacao']):'Nenhuma leitura recebida') ?></strong></p>
<section class="card"><h2>Condições atuais</h2><div id="painel-atuais" class="sensores-grid"></div></section>
<div class="graficos-grid"><?php foreach($series as $tipo=>$serie): ?><section class="card"><h2><?= escapar(SensorModel::rotulo($tipo)) ?> · 24 horas</h2><div class="grafico-area"><canvas id="painel-<?= $tipo ?>" role="img" aria-label="Histórico de <?= escapar(SensorModel::rotulo($tipo)) ?>"></canvas></div><p id="vazio-<?= $tipo ?>"><?= $serie['total']?'Médias por intervalo, separadas por sensor.':'Nenhuma leitura no período.' ?></p></section><?php endforeach ?><section class="card"><h2>Alertas nos últimos 7 dias</h2><div class="grafico-area"><canvas id="painel-alertas-grafico" role="img" aria-label="Quantidade de alertas por dia"></canvas></div></section></div>
<section class="card"><h2>Alertas recentes</h2><div id="painel-alertas"></div><a class="btn-secundario" href="../graficos/alerta.php?estoque_id=<?= $estoqueId ?>">Consultar alertas</a></section>
<script>iniciarPainel(<?= jsonParaScript($resumo) ?>,<?= jsonParaScript($series) ?>,<?= jsonParaScript(urlApp('routes/painel_dados.php').'?estoque_id='.$estoqueId) ?>,<?= jsonParaScript(urlApp('routes/grafico_dados.php')) ?>,<?= $estoqueId ?>);</script>
<?php require __DIR__.'/../partials/rodape.php'; ?>
