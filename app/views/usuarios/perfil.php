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
$erroSenha=isset($r['erros']['senha_atual'])||isset($r['erros']['senha'])||isset($r['erros']['confirmar_senha']);
$scriptsRodape=['conta.js'];
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Minha conta</h1><p>Atualize seus dados pessoais, foto, senha e preferência de aparência.</p></div></header>
<?php if($r['mensagem']): ?><p class="msg-sucesso" role="status"><?= escapar($r['mensagem']) ?></p><?php endif ?>
<?php if($r['erros']): ?><div class="msg-erro" role="alert">Confira os campos indicados.<?php if(isset($r['erros']['foto'])): ?> A foto precisa ser selecionada novamente.<?php endif ?><?php foreach($r['erros'] as $e): ?><p><?= escapar($e) ?></p><?php endforeach ?></div><?php endif ?>

<section class="card conta-resumo" aria-label="Informações da conta">
    <?= avatarUsuario($usuario) ?>
    <div class="conta-resumo-texto"><h2><?= escapar($usuario['nome']) ?></h2>
        <p><span class="badge badge-<?= $usuario['tipo']==='admin'?'info':'normal' ?>"><?= escapar(rotuloTipoUsuario($usuario['tipo'])) ?></span> <span class="badge badge-<?= escapar($usuario['status']) ?>"><?= $usuario['status']==='ativo'?'Ativo':'Inativo' ?></span></p></div>
    <dl class="conta-dados"><dt>Login</dt><dd><?= escapar($usuario['login']) ?></dd><dt>Conta criada em</dt><dd><?= escapar(dataBr($usuario['criado_em'],false)) ?></dd><dt>Estoques autorizados</dt><dd><?= $usuario['tipo']==='admin'?'Todos':count($estoques) ?></dd></dl>
</section>

<form method="post" enctype="multipart/form-data" class="card" data-edicao id="form-conta">
<input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>">
<fieldset class="foto-conta"><legend>Foto de perfil</legend>
    <div class="foto-conta-corpo">
        <div class="foto-previa" id="foto-previa"><?= avatarUsuario($usuario) ?></div>
        <div class="foto-controles">
            <input type="file" name="foto" id="campo-foto" accept="image/png,image/jpeg" class="arquivo-oculto" aria-describedby="foto-ajuda foto-erro">
            <div class="foto-botoes">
                <label for="campo-foto" class="btn-secundario btn-arquivo" tabindex="0" role="button" id="botao-foto">Escolher foto</label>
                <button type="button" class="btn-secundario" id="cancelar-foto" hidden>Cancelar seleção</button>
                <?php if($usuario['foto']): ?><label class="check-linha" id="rotulo-remover"><input type="checkbox" name="remover_foto" value="1">Remover minha foto</label><?php endif ?>
            </div>
            <p class="texto-auxiliar" id="foto-ajuda">JPG ou PNG, até 2 MB. A imagem só é enviada ao clicar em “Salvar alterações”.</p>
            <p class="campo-erro" id="foto-erro" role="alert"><?= isset($r['erros']['foto'])?escapar($r['erros']['foto']):'' ?></p>
            <p class="texto-auxiliar" id="foto-nome" hidden></p>
        </div>
    </div>
</fieldset>
<fieldset><legend>Dados pessoais e aparência</legend><div class="form-grid">
<label>Nome completo *<input name="nome" required minlength="3" maxlength="100" value="<?= escapar($nome) ?>"><?php if(isset($r['erros']['nome'])): ?><small class="campo-erro"><?= escapar($r['erros']['nome']) ?></small><?php endif ?></label>
<label>Aparência<select name="tema"><?php foreach(['automatico'=>'Acompanhar o dispositivo','claro'=>'Claro','escuro'=>'Escuro'] as $v=>$label): ?><option value="<?= $v ?>" <?= ($r['erros']?($_POST['tema']??$usuario['tema']):$usuario['tema'])===$v?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select><small>Salvo na sua conta e aplicado em todos os seus dispositivos.</small></label>
</div></fieldset>
<details class="secao-senha" <?= $erroSenha?'open':'' ?>><summary>Alterar senha</summary>
<p class="texto-auxiliar">Deixe os campos vazios para manter a senha atual. Use pelo menos 8 caracteres, com maiúscula, minúscula, número e símbolo.</p><div class="form-grid">
<?php foreach(['senha_atual'=>'Senha atual','senha'=>'Nova senha','confirmar_senha'=>'Confirmar nova senha'] as $campo=>$label): ?><label><?= $label ?><input type="password" name="<?= $campo ?>" maxlength="72" autocomplete="<?= $campo==='senha_atual'?'current-password':'new-password' ?>"><?php if(isset($r['erros'][$campo])): ?><small class="campo-erro"><?= escapar($r['erros'][$campo]) ?></small><?php endif ?></label><?php endforeach ?>
</div></details>
<div class="form-acoes"><button>Salvar alterações</button><a class="btn-secundario" href="perfil.php">Cancelar</a></div>
</form>

<section class="card"><h2>Estoques autorizados</h2>
<?php if(!$estoques): ?><p class="estado-vazio">Nenhum estoque autorizado. Solicite a vinculação ao administrador.</p><?php else: ?>
<?php if($usuario['tipo']==='admin'): ?><p class="texto-auxiliar">Administradores têm acesso a todos os estoques cadastrados.</p><?php endif ?>
<div class="estoques-compactos"><?php foreach($estoques as $e): ?><a class="estoque-mini" href="../estoques/detalhes.php?id=<?= (int)$e['id'] ?>"><strong><?= escapar($e['nome']) ?></strong><span><?= escapar($e['localizacao']?:'Localização não informada') ?></span></a><?php endforeach ?></div><?php endif ?></section>
<?php require __DIR__.'/../partials/rodape.php'; ?>
