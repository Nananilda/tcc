<?php
require_once __DIR__ . '/../../../includes/auth.php'; exigirAdmin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
$controller = new UsuarioController($pdo, true, (int)$_SESSION['usuario_id']);
$erros=[];
/* Mesma tela para as duas ações: desativar (bloqueia o acesso, preserva o histórico) e excluir (definitivo, só sem histórico). */
$excluir = ($_POST['modo'] ?? $_GET['modo'] ?? '') === 'excluir';
$padraoVoltar = 'app/views/usuarios/gestao_usuarios.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if(!validarCSRF($_POST['csrf_token']??'')) $erros[]='Token de segurança inválido.';
    elseif(($_POST['confirmacao']??'')!==($excluir?'excluir':'desativar')) $erros[]=$excluir?'Confirme a exclusão do usuário.':'Confirme a desativação do usuário.';
    else {
        $r=$excluir ? $controller->apagar((int)($_POST['id']??0)) : $controller->excluir((int)($_POST['id']??0));
        $erros=$r['erros'];
        /* Sucesso: volta à página de origem (Post/Redirect/Get: atualizar a página não repete a ação). */
        if(!$erros && $r['mensagem']!=='') redirecionarComMensagem(urlVoltar($padraoVoltar),$r['mensagem']);
    }
}
$usuarios=$controller->listar(); $tituloPagina=$excluir?'Excluir usuário':'Desativar usuário';
$selecionado=(int)($_POST['id']??$_GET['id']??0);
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1><?= escapar($tituloPagina) ?></h1><p><?= $excluir ? 'A exclusão é definitiva e só é possível para usuários sem histórico (acessos ou atendimento de alertas). Para bloquear o acesso preservando os registros, use Desativar.' : 'O acesso será bloqueado. Os registros de reconhecimento, resolução e auditoria serão preservados.' ?></p></div></header>
<?= mensagensPagina('',$erros) ?>
<form method="post" class="card card-perigo" data-confirmar="<?= $excluir ? 'Excluir definitivamente este usuário? Esta ação não pode ser desfeita.' : 'Desativar o usuário selecionado? O acesso será interrompido. Todos os históricos serão preservados.' ?>"<?= $excluir ? ' data-confirmar-rotulo="Confirmar exclusão"' : '' ?>><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="modo" value="<?= $excluir?'excluir':'desativar' ?>"><?= campoVoltar() ?><label for="usuario-id">Usuário *</label><select id="usuario-id" name="id" required><?php foreach($usuarios as $u): if($u['administrador_principal'])continue; ?><option value="<?= (int)$u['id'] ?>" <?= $selecionado===(int)$u['id']?'selected':'' ?>><?= escapar($u['nome'].' — '.$u['login'].' ('.$u['status'].')') ?></option><?php endforeach ?></select><input type="hidden" name="confirmacao" value="<?= $excluir?'excluir':'desativar' ?>"><div class="form-acoes"><button type="submit" class="btn-perigo"><?= $excluir?'Excluir usuário':'Desativar usuário' ?></button><?= linkCancelar($padraoVoltar) ?></div></form>
<?php require __DIR__.'/../partials/rodape.php'; ?>
