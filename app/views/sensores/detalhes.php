<?php
require_once __DIR__.'/../../../includes/auth.php';exigirLogin();
require_once __DIR__.'/../../models/SensorModel.php';
$sensor=(new SensorModel($pdo))->buscarPorId((int)($_GET['id']??0));
if(!$sensor){http_response_code(404);$tituloPagina='Sensor indisponível';require __DIR__.'/../partials/cabecalho.php';echo '<h1>Sensor indisponível</h1><p class="estado-vazio">Sensor inexistente ou sem permissão de acesso.</p>';require __DIR__.'/../partials/rodape.php';exit;}
$id=(int)$sensor['id'];$tituloPagina=$sensor['nome'];$cssPagina=['graficos.css','monitoramento.css'];$scriptsHead=['vendor/chart.umd.js','graficos.js','monitoramento.js'];
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1><?= escapar($sensor['nome']) ?></h1><p><?= escapar(SensorModel::rotuloComUnidade($sensor['tipo'])) ?></p></div><?php if(ehAdmin()): ?><a class="btn-secundario" href="editar_sensores.php?id=<?= $id ?>">Editar sensor</a><?php endif ?></header>
<?php
require_once __DIR__.'/../../models/LeituraModel.php';
$stOrigem=$pdo->prepare('SELECT origem FROM leitura_sensor WHERE sensor_id=? ORDER BY lido_em DESC,id DESC LIMIT 1');$stOrigem->execute([$id]);$origemUltima=$stOrigem->fetchColumn();
$u=SensorModel::unidade($sensor['tipo']);
?>
<div class="detalhes-grade"><section class="card"><h2>Identificação</h2>
<dl class="lista-dados"><dt>Estoque</dt><dd><a href="../estoques/detalhes.php?id=<?= (int)$sensor['estoque_id'] ?>"><?= escapar($sensor['estoque_nome']) ?></a></dd>
<dt>Tipo de medição</dt><dd><?= escapar(SensorModel::rotuloComUnidade($sensor['tipo'])) ?></dd>
<dt>Localização</dt><dd><?= escapar($sensor['localizacao']?:'Não informada') ?></dd>
<dt>Situação cadastral</dt><dd><span class="badge badge-<?= escapar($sensor['status']) ?>"><?= $sensor['status']==='ativo'?'Ativo':'Inativo' ?></span></dd></dl>
<p class="texto-auxiliar">Ativo significa habilitado no sistema; inativo significa desativado administrativamente.</p></section>
<section class="card"><h2>Comunicação</h2>
<dl class="lista-dados"><dt>Situação</dt><dd><span class="badge badge-<?= escapar($sensor['comunicacao']) ?>"><?= escapar(['online'=>'Online','sem_comunicacao'=>'Sem comunicação','inativo'=>'Monitoramento desativado'][$sensor['comunicacao']]) ?></span></dd>
<dt>Prazo para novo envio</dt><dd><?= (int)$sensor['intervalo_comunicacao'] ?> segundos</dd>
<dt>Última leitura</dt><dd><strong><?= $sensor['ultimo_valor']===null?'Sem leitura':escapar(number_format((float)$sensor['ultimo_valor'],2,',','.').' '.$u) ?></strong></dd>
<dt>Recebida em</dt><dd><?= $sensor['ultima_leitura']?escapar(dataBr($sensor['ultima_leitura'])):'Aguardando a primeira leitura.' ?><?= $sensor['ultimo_valor']!==null&&$sensor['comunicacao']!=='online'?' <span class="badge badge-sem_comunicacao">Desatualizada</span>':'' ?></dd>
<dt>Origem do dado</dt><dd><?= $origemUltima===false?'—':escapar(LeituraModel::rotuloOrigem($origemUltima)) ?></dd></dl></section>
<section class="card"><h2>Limites ambientais</h2><p class="texto-auxiliar">Limites configurados para este sensor pelo administrador. Não são recomendações técnicas universais: devem refletir o produto e o ambiente monitorados.</p><?php foreach(['atencao'=>'Atenção','critico'=>'Crítico'] as $nivel=>$rotulo): ?><div class="limite-<?= $nivel ?>"><h3><?= $rotulo ?></h3><p>Inferior: <?= $sensor['limite_'.$nivel.'_min']===null?'Não configurado':escapar($sensor['limite_'.$nivel.'_min'].' '.$u) ?></p><p>Superior: <?= $sensor['limite_'.$nivel.'_max']===null?'Não configurado':escapar($sensor['limite_'.$nivel.'_max'].' '.$u) ?></p></div><?php endforeach ?></section></div>
<div class="actions"><a class="btn-secundario" href="listar_sensores.php">Voltar aos sensores</a><a class="btn-secundario" href="../graficos/graficos_sensores.php?sensor_id=<?= $id ?>">Monitoramento</a></div>
<?php $escopoHistorico=['sensor_id'=>$id,'sensor'=>$sensor['tipo']];$entidade='sensor';require __DIR__.'/../partials/historico_entidade.php';require __DIR__.'/../partials/rodape.php'; ?>
