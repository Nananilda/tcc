<?php
require_once __DIR__.'/../../../includes/auth.php';exigirLogin();
require_once __DIR__.'/../../controllers/ContaController.php';
require_once __DIR__.'/../../models/EstoqueModel.php';
$c=new ContaController($pdo,(int)$_SESSION['usuario_id']);$r=['erros'=>[],'mensagem'=>''];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $r=validarCSRF($_POST['csrf_token']??'') ? $c->salvar($_POST,$_FILES) : ['erros'=>['Confirmação de segurança inválida. Recarregue a página.'],'mensagem'=>''];
}
$usuario=$c->consultar();$estoques=(new EstoqueModel($pdo))->listarTodos();$tituloPagina='Minha conta';
$nome=$r['erros']&&is_string($_POST['nome']??null)?$_POST['nome']:$usuario['nome'];
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Minha conta</h1><p>Atualize seus dados pessoais, foto, senha e preferência de aparência.</p></div></header>
<?php if($r['mensagem']): ?><p class="msg-sucesso" role="status"><?= escapar($r['mensagem']) ?></p><?php endif ?>
<?php if($r['erros']): ?><div class="msg-erro" role="alert">Confira os campos indicados. A foto precisa ser selecionada novamente.<?php foreach($r['erros'] as $e): ?><p><?= escapar($e) ?></p><?php endforeach ?></div><?php endif ?>
<form method="post" enctype="multipart/form-data" class="card" data-edicao>
<input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>">
<div class="identidade-conta"><?= avatarUsuario($usuario) ?><div><h2><?= escapar($usuario['nome']) ?></h2><p><?= escapar(rotuloTipoUsuario($usuario['tipo'])) ?> · <?= $usuario['status']==='ativo'?'Ativo':'Inativo' ?></p><p>Login: <?= escapar($usuario['login']) ?> · Conta criada em <?= escapar(dataBr($usuario['criado_em'],false)) ?></p></div></div>
<fieldset><legend>Dados pessoais</legend><div class="form-grid">
<label>Nome completo *<input name="nome" required minlength="3" maxlength="100" value="<?= escapar($nome) ?>"><?php if(isset($r['erros']['nome'])): ?><small class="campo-erro"><?= escapar($r['erros']['nome']) ?></small><?php endif ?></label>
<label>Aparência<select name="tema"><?php foreach(['automatico'=>'Acompanhar o dispositivo','claro'=>'Claro','escuro'=>'Escuro'] as $v=>$label): ?><option value="<?= $v ?>" <?= ($r['erros']?($_POST['tema']??$usuario['tema']):$usuario['tema'])===$v?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
<label>Adicionar ou substituir foto<input type="file" name="foto" accept="image/png,image/jpeg"><small>JPG ou PNG, até 2 MB.</small><?php if(isset($r['erros']['foto'])): ?><small class="campo-erro"><?= escapar($r['erros']['foto']) ?></small><?php endif ?></label>
<?php if($usuario['foto']): ?><label class="check-linha"><input type="checkbox" name="remover_foto" value="1">Remover minha foto</label><?php endif ?>
</div></fieldset>
<fieldset><legend>Alterar senha</legend><p>Deixe os campos abaixo vazios para manter a senha atual. Use pelo menos 8 caracteres, com maiúscula, minúscula, número e símbolo.</p><div class="form-grid">
<?php foreach(['senha_atual'=>'Senha atual','senha'=>'Nova senha','confirmar_senha'=>'Confirmar nova senha'] as $campo=>$label): ?><label><?= $label ?><input type="password" name="<?= $campo ?>" maxlength="72" autocomplete="<?= $campo==='senha_atual'?'current-password':'new-password' ?>"><?php if(isset($r['erros'][$campo])): ?><small class="campo-erro"><?= escapar($r['erros'][$campo]) ?></small><?php endif ?></label><?php endforeach ?>
</div></fieldset><div class="form-acoes"><button>Salvar alterações</button><a class="btn-secundario" href="perfil.php">Cancelar</a></div>
</form>
<section class="card"><h2>Estoques autorizados</h2><?php if(!$estoques): ?><p class="estado-vazio">Nenhum estoque autorizado. Solicite a vinculação ao administrador.</p><?php endif ?><ul><?php foreach($estoques as $e): ?><li><a href="../estoques/detalhes.php?id=<?= (int)$e['id'] ?>"><?= escapar($e['nome']) ?></a></li><?php endforeach ?></ul></section>
<?php require __DIR__.'/../partials/rodape.php'; ?>
