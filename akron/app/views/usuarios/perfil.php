<?php
require_once __DIR__.'/../../../includes/auth.php';exigirLogin();
require_once __DIR__.'/../../controllers/ContaController.php';
require_once __DIR__.'/../../models/EstoqueModel.php';
$c=new ContaController($pdo,(int)$_SESSION['usuario_id']);$r=['erros'=>[],'mensagem'=>''];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $r=validarCSRF($_POST['csrf_token']??'') ? $c->salvar($_POST,$_FILES) : ['erros'=>['Confirmação de segurança inválida. Recarregue a página.'],'mensagem'=>''];
}
$usuario=$c->consultar();$estoques=(new EstoqueModel($pdo))->listarTodos();$tituloPagina='Configurações pessoais';
$nome=$r['erros']&&is_string($_POST['nome']??null)?$_POST['nome']:$usuario['nome'];
$erroSenha=isset($r['erros']['senha_atual'])||isset($r['erros']['senha'])||isset($r['erros']['confirmar_senha']);
$scriptsRodape=['conta.js'];$cssPagina=['usuarios.css'];
$temaAtual=$r['erros']?($_POST['tema']??$usuario['tema']):$usuario['tema'];
if(!in_array($temaAtual,['automatico','claro','escuro'],true))$temaAtual='automatico';
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Configurações pessoais</h1><p>Gerencie seu perfil, a aparência do sistema, a segurança da conta e seus acessos.</p></div></header>
<?php if($r['mensagem']): ?><p class="msg-sucesso" role="status"><?= escapar($r['mensagem']) ?></p><?php endif ?>
<?php if($r['erros']): ?><div class="msg-erro" role="alert">Confira os campos indicados.<?php if(isset($r['erros']['foto'])): ?> A foto precisa ser selecionada novamente.<?php endif ?><?php foreach($r['erros'] as $e): ?><p><?= escapar($e) ?></p><?php endforeach ?></div><?php endif ?>

<nav class="config-indice" aria-label="Seções das configurações">
    <a href="#perfil"><?= iconeSvg('perfil') ?>Perfil</a>
    <a href="#aparencia"><?= iconeSvg('paleta') ?>Aparência</a>
    <a href="#seguranca"><?= iconeSvg('seguranca') ?>Segurança</a>
    <a href="#acessos"><?= iconeSvg('chave') ?>Acessos</a>
</nav>

<form method="post" enctype="multipart/form-data" data-edicao id="form-conta">
<input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>">

<section class="card config-secao" id="perfil" aria-labelledby="titulo-perfil">
    <header class="config-secao-topo"><span class="config-secao-icone"><?= iconeSvg('perfil') ?></span><div><h2 id="titulo-perfil">Perfil</h2><p>Sua foto e o nome exibido no sistema.</p></div></header>
    <div class="perfil-identidade">
        <div class="foto-previa" id="foto-previa"><?= avatarUsuario($usuario) ?></div>
        <div class="foto-controles">
            <p class="perfil-nome"><?= escapar($usuario['nome']) ?></p>
            <p class="perfil-selos"><span class="badge badge-<?= $usuario['tipo']==='admin'?'info':'normal' ?>"><?= escapar(rotuloTipoUsuario($usuario['tipo'])) ?></span> <span class="badge badge-<?= escapar($usuario['status']) ?>"><?= $usuario['status']==='ativo'?'Ativo':'Inativo' ?></span></p>
            <input type="file" name="foto" id="campo-foto" accept="image/png,image/jpeg" class="arquivo-oculto" aria-describedby="foto-ajuda foto-erro">
            <div class="foto-botoes">
                <label for="campo-foto" class="btn-secundario btn-arquivo" tabindex="0" role="button" id="botao-foto">Alterar foto</label>
                <button type="button" class="btn-secundario" id="cancelar-foto" hidden>Cancelar seleção</button>
                <?php if($usuario['foto']): ?><label class="check-linha" id="rotulo-remover"><input type="checkbox" name="remover_foto" value="1">Remover foto e usar o avatar padrão</label><?php endif ?>
            </div>
            <p class="texto-auxiliar" id="foto-ajuda">JPG ou PNG, até 2 MB. A imagem só é enviada ao clicar em “Salvar alterações”.</p>
            <p class="campo-erro" id="foto-erro" role="alert"><?= isset($r['erros']['foto'])?escapar($r['erros']['foto']):'' ?></p>
            <p class="texto-auxiliar" id="foto-nome" hidden></p>
        </div>
    </div>
    <div class="form-grid">
        <label>Nome completo *<input name="nome" required minlength="3" maxlength="100" value="<?= escapar($nome) ?>"><?php if(isset($r['erros']['nome'])): ?><small class="campo-erro"><?= escapar($r['erros']['nome']) ?></small><?php endif ?></label>
    </div>
    <dl class="conta-dados"><dt>Login</dt><dd><?= escapar($usuario['login']) ?></dd><dt>Conta criada em</dt><dd><?= escapar(dataBr($usuario['criado_em'],false)) ?></dd><dt>Estoques autorizados</dt><dd><?= $usuario['tipo']==='admin'?'Todos':count($estoques) ?></dd></dl>
</section>

<section class="card config-secao" id="aparencia" aria-labelledby="titulo-aparencia">
    <header class="config-secao-topo"><span class="config-secao-icone"><?= iconeSvg('paleta') ?></span><div><h2 id="titulo-aparencia">Aparência</h2><p>O tema vale para todas as páginas e fica salvo na sua conta.</p></div></header>
    <fieldset class="tema-opcoes" aria-describedby="tema-estado">
        <legend class="sr-only">Tema do sistema</legend>
        <?php foreach(['claro'=>['Claro','sol','Fundo claro, ideal para ambientes iluminados.'],'escuro'=>['Escuro','lua','Fundo escuro, mais confortável com pouca luz.'],'automatico'=>['Automático','monitor','Acompanha a configuração do seu dispositivo.']] as $valor=>[$rotulo,$icone,$ajuda]): ?>
        <label class="tema-opcao tema-<?= $valor ?>">
            <input type="radio" name="tema" value="<?= $valor ?>" <?= $temaAtual===$valor?'checked':'' ?>>
            <span class="tema-miniatura" aria-hidden="true"><span class="mini-barra"></span><span class="mini-corpo"><i></i><i></i></span></span>
            <span class="tema-rotulo"><?= iconeSvg($icone) ?><strong><?= $rotulo ?></strong></span>
            <span class="tema-ajuda"><?= $ajuda ?></span>
            <span class="tema-marca" aria-hidden="true"><?= iconeSvg('seguranca') ?></span>
        </label>
        <?php endforeach ?>
    </fieldset>
    <p class="texto-auxiliar" id="tema-estado" role="status" aria-live="polite">Ao escolher, o tema é aplicado e salvo na hora.</p>
</section>

<section class="card config-secao" id="seguranca" aria-labelledby="titulo-seguranca">
    <header class="config-secao-topo"><span class="config-secao-icone"><?= iconeSvg('seguranca') ?></span><div><h2 id="titulo-seguranca">Segurança</h2><p>Altere sua senha. Deixe os campos vazios para manter a atual.</p></div></header>
    <details class="secao-senha" <?= $erroSenha?'open':'' ?>><summary>Alterar senha</summary>
    <p class="texto-auxiliar">Use pelo menos 8 caracteres, com maiúscula, minúscula, número e símbolo.</p><div class="form-grid">
    <?php foreach(['senha_atual'=>'Senha atual','senha'=>'Nova senha','confirmar_senha'=>'Confirmar nova senha'] as $campo=>$label): ?><label><?= $label ?><input type="password" name="<?= $campo ?>" maxlength="72" autocomplete="<?= $campo==='senha_atual'?'current-password':'new-password' ?>"><?php if(isset($r['erros'][$campo])): ?><small class="campo-erro"><?= escapar($r['erros'][$campo]) ?></small><?php endif ?></label><?php endforeach ?>
    </div></details>
</section>

<div class="form-acoes config-acoes"><button>Salvar alterações</button><a class="btn-secundario" href="perfil.php">Cancelar</a></div>
</form>

<section class="card config-secao" id="acessos" aria-labelledby="titulo-acessos">
    <header class="config-secao-topo"><span class="config-secao-icone"><?= iconeSvg('chave') ?></span><div><h2 id="titulo-acessos">Acessos</h2><p>Estoques que você pode consultar.</p></div></header>
<?php if(!$estoques): ?><p class="estado-vazio">Nenhum estoque autorizado. Solicite a vinculação ao administrador.</p><?php else: ?>
<?php if($usuario['tipo']==='admin'): ?><p class="texto-auxiliar">Administradores têm acesso a todos os estoques cadastrados.</p><?php endif ?>
<div class="estoques-compactos"><?php foreach($estoques as $e): ?><a class="estoque-mini" href="../estoques/detalhes.php?id=<?= (int)$e['id'] ?>"><strong><?= escapar($e['nome']) ?></strong><span><?= escapar($e['localizacao']?:'Localização não informada') ?></span></a><?php endforeach ?></div><?php endif ?></section>
<?php require __DIR__.'/../partials/rodape.php'; ?>
