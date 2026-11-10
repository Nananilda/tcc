<?php
require_once __DIR__.'/../../../includes/auth.php';exigirLogin();
require_once __DIR__.'/../../models/EstoqueModel.php';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;exigirEstoque($pdo,$id);
$model=new EstoqueModel($pdo);$estoque=$model->buscarPorId($id);
$resumos=$model->listarTodos();$resumo=current(array_filter($resumos,fn($e)=>(int)$e['id']===$id));
$sensores=(new LeituraModel($pdo))->atuais($id);
$tituloPagina=$estoque['nome'];$cssPagina=['graficos.css','monitoramento.css'];$scriptsHead=['vendor/chart.umd.js','graficos.js','monitoramento.js'];
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1><?= escapar($estoque['nome']) ?></h1><p><?= escapar($estoque['localizacao']?:'Localização não informada') ?></p><p><?= escapar($estoque['descricao']?:'Acompanhamento ambiental do estoque.') ?></p></div><?php if(ehAdmin()): ?><a class="btn-secundario" href="formulario.php?id=<?= $id ?>">Editar estoque</a><?php endif ?></header>
<section class="card condicao-destaque amb-<?= escapar($resumo['condicao_ambiental']) ?>"><h2>Estado do estoque</h2>
<div class="estado-tripla">
<div><span class="estado-rotulo">Condição ambiental</span><span class="badge badge-<?= escapar($resumo['condicao_ambiental']) ?>"><?= escapar($resumo['condicao_ambiental_rotulo']) ?></span>
<small><?php if($resumo['condicao_ambiental']==='indisponivel'): ?>Não há leituras válidas e recentes para avaliar.<?php elseif((int)$resumo['sensores_avaliados']<(int)$resumo['sensores_ativos']): ?>Avaliação baseada em <?= (int)$resumo['sensores_avaliados'] ?> de <?= (int)$resumo['sensores_ativos'] ?> sensores ativos.<?php else: ?>Avaliação baseada em todos os sensores ativos.<?php endif ?></small></div>
<div><span class="estado-rotulo">Comunicação</span><span class="badge badge-<?= escapar($resumo['comunicacao_estado']) ?>"><?= escapar($resumo['comunicacao_rotulo']) ?></span>
<small>Sensores online: <?= (int)$resumo['sensores_online'] ?> de <?= (int)$resumo['sensores_ativos'] ?><?= $resumo['sensores_sem_comunicacao_nomes']?' · Sem comunicação: '.escapar(implode(', ',$resumo['sensores_sem_comunicacao_nomes'])):'' ?></small></div>
<div><span class="estado-rotulo">Situação cadastral</span><span class="badge badge-<?= escapar($estoque['status']) ?>"><?= escapar($resumo['situacao_cadastral_rotulo']) ?></span>
<small><?= (int)$resumo['sensores_total'] ?> sensor(es) cadastrado(s)<?= !empty($estoque['demonstracao'])?' · Dados de demonstração':'' ?></small></div>
<div><span class="estado-rotulo">Alertas ativos</span><strong class="contador-alertas<?= (int)$resumo['alertas_ativos']?' com-alertas':'' ?>"><?= (int)$resumo['alertas_ativos'] ?></strong>
<small><?= (int)$resumo['alertas_criticos_pendentes'] ?> crítico(s) pendente(s)</small></div>
</div></section>
<nav class="actions"><a class="btn-secundario" href="../sensores/listar_sensores.php?estoque_id=<?= $id ?>">Sensores do estoque</a><a class="btn-secundario" href="../graficos/graficos_sensores.php?estoque_id=<?= $id ?>">Monitoramento</a><a class="btn-secundario" href="listar.php">Voltar aos estoques</a></nav>
<section class="card"><h2>Sensores e leituras atuais</h2><div id="estoque-atuais" class="sensores-grid"></div></section><script>mostrarAtuais('estoque-atuais',<?= jsonParaScript($sensores) ?>);</script>
<?php $escopoHistorico=['estoque_id'=>$id];$entidade='estoque';require __DIR__.'/../partials/historico_entidade.php';require __DIR__.'/../partials/rodape.php'; ?>
