<?php
require_once __DIR__.'/../../../includes/auth.php';exigirLogin();
require_once __DIR__.'/../../models/EstoqueModel.php';
$estoques=(new EstoqueModel($pdo))->listarTodos();$tituloPagina='Estoques';
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Estoques</h1><p>Acompanhe as condições ambientais dos estoques autorizados.</p></div><?php if(ehAdmin()): ?><a class="btn-primario" href="formulario.php">Cadastrar estoque</a><?php endif ?></header>
<?php if(!$estoques): ?><p class="estado-vazio">Nenhum estoque disponível. <?= ehAdmin()?'Cadastre o primeiro estoque para iniciar o monitoramento.':'Solicite a vinculação a um administrador.' ?></p><?php endif ?>
<div class="estoques-grade">
<?php foreach($estoques as $e): $classe=['Crítico'=>'critico','Atenção'=>'atencao','Normal'=>'normal'][$e['condicao']]??'info'; ?>
<article class="estoque-card condicao-<?= $classe ?>"><span class="badge badge-<?= $classe ?>"><?= escapar($e['condicao']) ?></span><h2><a class="estoque-link" href="detalhes.php?id=<?= (int)$e['id'] ?>"><?= escapar($e['nome']) ?></a></h2><p><?= escapar($e['localizacao']?:'Localização não informada') ?></p><p><?= (int)$e['sensores_total'] ?> sensores cadastrados · <?= (int)$e['sensores_online'] ?> em funcionamento</p><p><?= (int)$e['alertas_ativos'] ?> alertas ativos</p><span class="badge badge-<?= escapar($e['status']) ?>"><?= $e['status']==='ativo'?'Ativo':'Inativo' ?></span><?php if(!$e['sensores_total']): ?><p>Nenhum sensor cadastrado neste estoque.</p><?php endif ?><div class="acoes-card"><a href="detalhes.php?id=<?= (int)$e['id'] ?>">Ver detalhes</a><?php if(ehAdmin()): ?><a href="formulario.php?id=<?= (int)$e['id'] ?>">Editar estoque</a><?php endif ?></div></article>
<?php endforeach ?>
</div>
<?php require __DIR__.'/../partials/rodape.php'; ?>
