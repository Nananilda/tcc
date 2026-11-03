<?php
require_once __DIR__.'/../../../includes/auth.php'; exigirAdmin();
require_once __DIR__.'/../../config/conexao.php';
require_once __DIR__.'/../../controllers/EstoqueController.php';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;
$controller=new EstoqueController($pdo); $resultado=$controller->processarRequisicao();
if (!$resultado['erros'] && isset($resultado['id'])) $id=(int)$resultado['id'];
$estoque=$id?(new EstoqueModel($pdo))->buscarPorId($id):['nome'=>'','descricao'=>'','localizacao'=>'','status'=>'ativo'];
if (!$estoque) { http_response_code(404); exit('Estoque não encontrado.'); }
if ($resultado['erros']) $estoque=array_replace($estoque,array_intersect_key($_POST,$estoque));
$tituloPagina=$id?'Editar estoque':'Cadastrar estoque'; $secaoTopbar='Estoques';
require __DIR__.'/../partials/cabecalho.php';
?>
<div class="page-header"><div><h1><?= escapar($tituloPagina) ?></h1><p class="subtitulo">Organize o local monitorado. A desativação preserva todo o histórico.</p></div></div>
<?php if ($resultado['mensagem']): ?><p class="msg-sucesso" role="status"><?= escapar($resultado['mensagem']) ?></p><?php endif; ?>
<?php if ($resultado['erros']): ?><div class="msg-erro" role="alert"><ul><?php foreach ($resultado['erros'] as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card">
<input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="acao" value="<?= $id?'atualizar':'cadastrar' ?>"><input type="hidden" name="id" value="<?= $id ?>">
<div class="form-grid"><div class="campo"><label for="nome">Nome *</label><input id="nome" name="nome" required minlength="3" maxlength="100" value="<?= escapar($estoque['nome']) ?>"></div>
<div class="campo"><label for="localizacao">Localização</label><input id="localizacao" name="localizacao" maxlength="150" value="<?= escapar($estoque['localizacao']) ?>"></div>
<div class="campo"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" maxlength="2000" rows="3"><?= escapar($estoque['descricao']) ?></textarea></div>
<div class="campo"><label for="status">Status *</label><select id="status" name="status"><option value="ativo" <?= $estoque['status']==='ativo'?'selected':'' ?>>Ativo</option><option value="inativo" <?= $estoque['status']==='inativo'?'selected':'' ?>>Inativo</option></select><small>Estoques inativos não recebem novas leituras.</small></div></div>
<div class="actions"><button type="submit" class="btn btn-primario">Salvar estoque</button><a class="btn btn-secundario" href="listar.php">Cancelar</a></div>
</form>
<?php if ($id && $estoque['status']==='ativo'): ?>
<form method="post" class="card" data-confirmar="Desativar <?= escapar($estoque['nome']) ?>? Novas leituras serão bloqueadas e o histórico será preservado."><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="acao" value="desativar"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="confirmacao" value="desativar"><button type="submit" class="btn btn-secundario">Desativar estoque</button></form>
<?php endif; ?>

<?php require __DIR__.'/../partials/rodape.php'; ?>
