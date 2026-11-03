<?php
require_once __DIR__ . '/../../../includes/auth.php'; exigirAdmin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
$controller = new UsuarioController($pdo, true, (int)$_SESSION['usuario_id']);
$erros=[]; $mensagem='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if(!validarCSRF($_POST['csrf_token']??'')) $erros[]='Token de segurança inválido.';
    elseif(($_POST['confirmacao']??'')!=='desativar') $erros[]='Digite desativar para confirmar.';
    else { $r=$controller->excluir((int)($_POST['id']??0)); $erros=$r['erros']; $mensagem=$r['mensagem']; }
}
$usuarios=$controller->listar(); $tituloPagina='Desativar usuário'; $secaoTopbar='Usuários';
require __DIR__.'/../partials/cabecalho.php';
?>
<h1>Desativar usuário</h1><p>O acesso será bloqueado. Os registros de reconhecimento, resolução e auditoria serão preservados.</p>
<?php foreach($erros as $erro): ?><p class="msg-erro" role="alert"><?= escapar($erro) ?></p><?php endforeach ?>
<?php if($mensagem): ?><p class="msg-sucesso" role="status"><?= escapar($mensagem) ?></p><?php endif ?>
<form method="post" class="card" data-confirmar="Desativar o registro selecionado? O acesso ou recebimento de dados será interrompido. Todos os históricos serão preservados."><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><label for="usuario-id">Usuário *</label><select id="usuario-id" name="id" required><?php foreach($usuarios as $u): if($u['administrador_principal'])continue; ?><option value="<?= (int)$u['id'] ?>" <?= (int)($_GET['id']??0)===(int)$u['id']?'selected':'' ?>><?= escapar($u['nome'].' — '.$u['login'].' ('.$u['status'].')') ?></option><?php endforeach ?></select><input type="hidden" name="confirmacao" value="desativar"><div class="form-acoes"><button>Desativar usuário</button><a class="btn-secundario" href="gestao_usuarios.php">Cancelar</a></div></form>
<?php require __DIR__.'/../partials/rodape.php'; ?>
