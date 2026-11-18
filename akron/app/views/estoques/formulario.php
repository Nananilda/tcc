<?php
require_once __DIR__.'/../../../includes/auth.php'; exigirAdmin();
require_once __DIR__.'/../../config/conexao.php';
require_once __DIR__.'/../../controllers/EstoqueController.php';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;
/* Retorno: a página de onde o usuário veio; no acesso direto, os detalhes (edição) ou a listagem (cadastro). */
$padraoVoltar=$id?'app/views/estoques/detalhes.php?id='.$id:'app/views/estoques/listar.php';
$resultado=(new EstoqueController($pdo))->processarRequisicao();
if (!$resultado['erros'] && $resultado['mensagem']!=='') {
    /* Sucesso: Post/Redirect/Get — atualizar a página não reenvia o formulário. Estoque excluído não tem mais detalhes. */
    redirecionarComMensagem(urlVoltar($padraoVoltar,($_POST['acao']??'')==='excluir'),$resultado['mensagem']);
}
$model=new EstoqueModel($pdo);
$estoque=$id?$model->buscarPorId($id):['nome'=>'','descricao'=>'','localizacao'=>'','status'=>'ativo'];
if (!$estoque) { http_response_code(404); $tituloPagina='Estoque indisponível'; require __DIR__.'/../partials/cabecalho.php'; ?>
<header class="page-header"><div><h1>Estoque indisponível</h1><p>O estoque não existe ou já foi excluído.</p></div><div class="actions"><?= linkVoltar('app/views/estoques/listar.php','Voltar aos estoques') ?></div></header>
<?php require __DIR__.'/../partials/rodape.php'; exit; }
if ($resultado['erros'] && in_array($_POST['acao']??'',['cadastrar','atualizar'],true)) $estoque=array_replace($estoque,array_intersect_key($_POST,$estoque));
$historico=$id?$model->contarHistorico($id):0;
$tituloPagina=$id?'Editar estoque':'Cadastrar estoque';
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1><?= escapar($tituloPagina) ?></h1><p>Organize o local monitorado. Desativar preserva todo o histórico; excluir só é possível para estoques sem sensores, leituras ou alertas.</p></div></header>
<?= mensagensPagina('',$resultado['erros']) ?>
<form method="post" class="card">
<input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="acao" value="<?= $id?'atualizar':'cadastrar' ?>"><input type="hidden" name="id" value="<?= $id ?>"><?= campoVoltar() ?>
<div class="form-grid"><div class="campo"><label for="nome">Nome *</label><input id="nome" name="nome" required minlength="3" maxlength="100" value="<?= escapar($estoque['nome']) ?>"></div>
<div class="campo"><label for="localizacao">Localização</label><input id="localizacao" name="localizacao" maxlength="150" value="<?= escapar($estoque['localizacao']) ?>"></div>
<div class="campo"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" maxlength="2000" rows="3"><?= escapar($estoque['descricao']) ?></textarea></div>
<div class="campo"><label for="status">Status *</label><select id="status" name="status"><option value="ativo" <?= $estoque['status']==='ativo'?'selected':'' ?>>Ativo</option><option value="inativo" <?= $estoque['status']==='inativo'?'selected':'' ?>>Inativo</option></select><small>Estoques inativos não recebem novas leituras.</small></div></div>
<div class="form-acoes"><button type="submit" class="btn-primario">Salvar estoque</button><?= linkCancelar($padraoVoltar) ?></div>
</form>
<?php if ($id): ?>
<section class="card card-perigo" aria-labelledby="zona-perigo"><h2 id="zona-perigo">Desativar ou excluir</h2>
<div class="form-acoes">
<?php if ($estoque['status']==='ativo'): ?>
<form method="post" data-confirmar="Desativar <?= escapar($estoque['nome']) ?>? Novas leituras serão bloqueadas e o histórico será preservado."><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="acao" value="desativar"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="confirmacao" value="desativar"><?= campoVoltar() ?><button type="submit" class="btn-secundario">Desativar estoque</button></form>
<?php endif; ?>
<?php if ($historico===0): ?>
<form method="post" data-confirmar="Excluir definitivamente este estoque? Esta ação não pode ser desfeita." data-confirmar-rotulo="Confirmar exclusão"><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="confirmacao" value="excluir"><?= campoVoltar() ?><button type="submit" class="btn-perigo">Excluir estoque</button></form>
<?php endif; ?>
</div>
<?php if ($historico>0): ?><p class="texto-auxiliar">A exclusão não está disponível: este estoque possui <?= (int)$historico ?> registro(s) vinculado(s) (sensores, leituras ou alertas). Use Desativar para encerrar o uso sem perder o histórico.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__.'/../partials/rodape.php'; ?>
