<?php
require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/AlertaController.php';
require_once __DIR__ . '/../../models/EstoqueModel.php';
$controller=new AlertaController($pdo,ehAdmin());
['mensagem'=>$mensagem,'erros'=>$erros]=$controller->processarRequisicao();
$alertas=[]; $contagem=['critico'=>0,'atencao'=>0,'info'=>0]; $total=0; $estoques=[]; $sensores=[];
$pagina=max(1,min(100000,(int)($_GET['pagina'] ?? 1))); $filtros=[];
try {
    $filtros=AlertaModel::validarFiltros($_GET);
    $porPagina=in_array((int)($_GET['por_pagina']??10),[10,25,50,100],true)?(int)($_GET['por_pagina']??10):10;
    $alertas=(new AlertaModel($pdo))->listarRecentes($porPagina,$filtros,$pagina);
    $total=$controller->contarTodos($filtros);
    $contagem=$controller->contarPorSeveridade($filtros);
    $estoques=(new EstoqueModel($pdo))->listarTodos();
    $sensores=$pdo->query('SELECT s.id,s.nome,s.estoque_id,GROUP_CONCAT(DISTINCT a.estoque_id) AS estoques_historicos FROM sensores s LEFT JOIN alertas a ON a.sensor_id=s.id AND '.escopoEstoque('a.estoque_id').' WHERE '.escopoEstoque('s.estoque_id').' OR a.id IS NOT NULL GROUP BY s.id,s.nome,s.estoque_id ORDER BY s.nome')->fetchAll();
} catch (InvalidArgumentException $e) { $erros[]=$e->getMessage();
} catch (PDOException $e) { error_log($e->getMessage()); $erros[]='Não foi possível carregar os alertas. Tente novamente.'; }
$csrf=gerarCSRF(); $tituloPagina='Alertas'; $cssPagina=['graficos.css'];
require __DIR__.'/../partials/cabecalho.php';
?>
<div class="page-heading"><div><h1>Alertas</h1><p class="subtitulo">Acompanhe irregularidades, reconheça ocorrências e registre o atendimento.</p></div>
<?php if(ehAdmin()): ?><a class="btn" href="novo_alerta.php">Adicionar alerta</a><?php endif; ?></div>
<?php if($mensagem): ?><div class="msg-sucesso" role="status"><?= escapar($mensagem) ?></div><?php endif; ?>
<?php foreach($erros as $erro): ?><div class="msg-erro" role="alert"><?= escapar($erro) ?></div><?php endforeach; ?>
<form method="get" class="card form-filtros">
<div><label for="estoque-id">Estoque</label><select id="estoque-id" name="estoque_id"><option value="">Todos autorizados</option>
<?php foreach($estoques as $e): ?><option value="<?= (int)$e['id'] ?>" <?= ($filtros['estoque_id'] ?? '')==$e['id']?'selected':'' ?>><?= escapar($e['nome']) ?></option><?php endforeach; ?></select></div>
<div><label for="sensor-id">Sensor</label><select id="sensor-id" name="sensor_id"><option value="">Todas as opções</option>
<?php foreach($sensores as $s): ?><option data-estoques="<?= escapar($s['estoque_id'].','.($s['estoques_historicos']??'')) ?>" value="<?= (int)$s['id'] ?>" <?= ($filtros['sensor_id'] ?? '')==$s['id']?'selected':'' ?>><?= escapar($s['nome']) ?></option><?php endforeach; ?></select></div>
<?php foreach(['severidade'=>['Gravidade',AlertaModel::SEVERIDADES],'status'=>['Status',AlertaModel::STATUS]] as $campo=>[$titulo,$opcoes]): ?>
<div><label for="<?= $campo ?>"><?= $titulo ?></label><select id="<?= $campo ?>" name="<?= $campo ?>"><option value="">Todas as opções</option><?php foreach($opcoes as $v=>$r): ?><option value="<?= $v ?>" <?= ($filtros[$campo] ?? '')===$v?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></div>
<?php endforeach; ?>
<div><label for="data-inicial">Data inicial</label><input type="date" id="data-inicial" name="data_inicial" value="<?= escapar($filtros['data_inicial'] ?? '') ?>"></div>
<div><label for="data-final">Data final</label><input type="date" id="data-final" name="data_final" value="<?= escapar($filtros['data_final'] ?? '') ?>"></div>
<label>Registros por página<select name="por_pagina"><?php foreach([10,25,50,100] as $n): ?><option value="<?= $n ?>" <?= ($porPagina??10)===$n?'selected':'' ?>><?= $n ?></option><?php endforeach ?></select></label><div class="form-acoes"><button type="submit" class="btn">Filtrar</button><a href="alerta.php" class="btn-secundario">Limpar</a></div>
</form>
<?php if(!empty($contagem['critico'])): ?><div class="faixa-critica" role="status"><strong><?= (int)$contagem['critico'] ?> alerta(s) crítico(s) ativo(s)</strong> nos filtros selecionados. Os críticos pendentes aparecem primeiro na lista.</div><?php endif ?>
<div class="alertas-cards" aria-label="Alertas ativos nos filtros selecionados">
<?php foreach(AlertaModel::SEVERIDADES as $grau=>$rotulo): ?><div class="card-severidade <?= $grau ?>"><strong class="valor"><?= $contagem[$grau] ?></strong><span><?= $rotulo ?> · ativos</span></div><?php endforeach; ?>
</div>
<div class="actions"><a class="btn-secundario" href="<?= urlApp('routes/exportar.php').'?'.escapar(http_build_query($filtros+['entidade'=>'alertas','formato'=>'csv'])) ?>">Baixar alertas em CSV</a><a class="btn-secundario" href="<?= urlApp('routes/exportar.php').'?'.escapar(http_build_query($filtros+['entidade'=>'alertas','formato'=>'pdf'])) ?>">Baixar alertas em PDF</a></div>
<section class="card"><h2>Histórico de alertas</h2><p><?= $total ?> registro(s). Reconhecimento, normalização e resolução são momentos diferentes do atendimento.</p>
<?php if(!$alertas): ?><p class="msg-vazio">Nenhum alerta encontrado para os filtros selecionados.</p><?php endif; ?>
<div class="alertas-grade"><?php $permitirAcoes=true;foreach($alertas as $a) require __DIR__.'/../partials/cartao_alerta.php'; ?></div>
<?php $totalPaginas=max(1,(int)ceil($total/$porPagina));$base=$filtros+['por_pagina'=>$porPagina];$de=$total?($pagina-1)*$porPagina+1:0;$ate=min($total,$pagina*$porPagina); ?>
<nav class="paginacao" aria-label="Páginas de alertas">
<?php if($pagina>1): ?><a class="btn-secundario" href="?<?= escapar(http_build_query($base+['pagina'=>1])) ?>" aria-label="Primeira página">«</a><a class="btn-secundario" href="?<?= escapar(http_build_query($base+['pagina'=>$pagina-1])) ?>">Anterior</a><?php endif; ?>
<span>Mostrando <?= $de ?>–<?= $ate ?> de <?= $total ?> · página <?= $pagina ?> de <?= $totalPaginas ?></span>
<?php if($pagina<$totalPaginas): ?><a class="btn-secundario" href="?<?= escapar(http_build_query($base+['pagina'=>$pagina+1])) ?>">Próxima</a><a class="btn-secundario" href="?<?= escapar(http_build_query($base+['pagina'=>$totalPaginas])) ?>" aria-label="Última página">»</a><?php endif; ?>
</nav></section>
<?php require __DIR__.'/../partials/rodape.php'; ?>
