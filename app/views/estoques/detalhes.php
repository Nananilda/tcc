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
<section class="card condicao-destaque"><h2>Condição geral: <?= escapar($resumo['condicao']) ?></h2><p>Situação cadastral: <?= $estoque['status']==='ativo'?'Ativo':'Inativo' ?> · <?= (int)$resumo['sensores_total'] ?> sensores · <?= (int)$resumo['sensores_online'] ?> online · <?= (int)$resumo['alertas_ativos'] ?> alertas ativos</p></section>
<nav class="actions"><a class="btn-secundario" href="../sensores/listar_sensores.php?estoque_id=<?= $id ?>">Sensores do estoque</a><a class="btn-secundario" href="../graficos/graficos_sensores.php?estoque_id=<?= $id ?>">Monitoramento</a><a class="btn-secundario" href="listar.php">Voltar aos estoques</a></nav>
<section class="card"><h2>Sensores e leituras atuais</h2><div id="estoque-atuais" class="sensores-grid"></div></section><script>mostrarAtuais('estoque-atuais',<?= jsonParaScript($sensores) ?>);</script>
<?php $escopoHistorico=['estoque_id'=>$id];$entidade='estoque';require __DIR__.'/../partials/historico_entidade.php';require __DIR__.'/../partials/rodape.php'; ?>
