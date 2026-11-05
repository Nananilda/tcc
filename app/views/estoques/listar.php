<?php
require_once __DIR__.'/../../../includes/auth.php';exigirLogin();
require_once __DIR__.'/../../models/EstoqueModel.php';
$estoques=(new EstoqueModel($pdo))->listarTodos();$tituloPagina='Estoques';
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Estoques</h1><p>Acompanhe as condições ambientais dos estoques autorizados. Cada cartão separa a condição ambiental, a comunicação dos sensores e a situação cadastral.</p></div><?php if(ehAdmin()): ?><a class="btn-primario" href="formulario.php">Cadastrar estoque</a><?php endif ?></header>
<?php if(!$estoques): ?><p class="estado-vazio">Nenhum estoque disponível. <?= ehAdmin()?'Cadastre o primeiro estoque para iniciar o monitoramento.':'Solicite a vinculação a um administrador.' ?></p><?php endif ?>
<div class="estoques-grade">
<?php foreach($estoques as $e): ?>
<article class="estoque-card amb-<?= escapar($e['condicao_ambiental']) ?><?= $e['status']==='inativo'?' estoque-inativo':'' ?>">
    <div class="estoque-topo">
        <h2><a class="estoque-link" href="detalhes.php?id=<?= (int)$e['id'] ?>"><?= escapar($e['nome']) ?></a></h2>
        <?php if(!empty($e['demonstracao'])): ?><span class="badge badge-demonstracao" title="Dados simulados para demonstração">Demonstração</span><?php endif ?>
    </div>
    <p class="estoque-local"><?= escapar($e['localizacao']?:'Localização não informada') ?></p>
    <dl class="estado-estoque">
        <dt>Condição ambiental</dt><dd><span class="badge badge-<?= escapar($e['condicao_ambiental']) ?>"><?= escapar($e['condicao_ambiental_rotulo']) ?></span></dd>
        <dt>Comunicação</dt><dd><span class="badge badge-<?= escapar($e['comunicacao_estado']) ?>"><?= escapar($e['comunicacao_rotulo']) ?></span></dd>
        <dt>Situação cadastral</dt><dd><span class="badge badge-<?= escapar($e['status']) ?>"><?= escapar($e['situacao_cadastral_rotulo']) ?></span></dd>
    </dl>
    <div class="estoque-metricas">
        <?php if(!(int)$e['sensores_total']): ?>
            <p class="aviso-sem-sensores">Nenhum sensor cadastrado neste estoque.</p>
        <?php else: ?>
            <?php if(!(int)$e['sensores_ativos']): ?><p class="aviso-sem-sensores">Nenhum sensor ativo (<?= (int)$e['sensores_total'] ?> inativo(s)).</p><?php else: ?><p><strong>Sensores online:</strong> <?= (int)$e['sensores_online'] ?> de <?= (int)$e['sensores_ativos'] ?><?php if((int)$e['sensores_total']>(int)$e['sensores_ativos']): ?> <span class="texto-auxiliar">(<?= (int)$e['sensores_total']-(int)$e['sensores_ativos'] ?> inativo(s))</span><?php endif ?></p><?php endif ?>
        <?php endif ?>
        <p class="alertas-linha<?= (int)$e['alertas_criticos_pendentes']?' tem-critico':'' ?>"><strong>Alertas ativos:</strong> <span class="contador-alertas<?= (int)$e['alertas_ativos']?' com-alertas':'' ?>"><?= (int)$e['alertas_ativos'] ?></span><?php if((int)$e['alertas_criticos_pendentes']): ?> <span class="badge badge-critico"><?= (int)$e['alertas_criticos_pendentes'] ?> crítico(s) pendente(s)</span><?php endif ?></p>
    </div>
    <div class="acoes-card"><a class="btn-secundario" href="detalhes.php?id=<?= (int)$e['id'] ?>">Ver detalhes</a><?php if(ehAdmin()): ?><a class="btn-secundario" href="formulario.php?id=<?= (int)$e['id'] ?>">Editar estoque</a><?php endif ?></div>
</article>
<?php endforeach ?>
</div>
<?php require __DIR__.'/../partials/rodape.php'; ?>
