<?php
require_once __DIR__.'/../../../includes/auth.php';exigirAdmin();
require_once __DIR__.'/../../controllers/UsuarioController.php';
$usuarios=(new UsuarioController($pdo,true,(int)$_SESSION['usuario_id']))->listar();
$estoques=$pdo->query('SELECT id,nome FROM estoques')->fetchAll(PDO::FETCH_KEY_PAIR);
$tituloPagina='Usuários';require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Usuários</h1><p>Gerencie os perfis e o acesso aos estoques.</p></div><a class="btn-primario" href="cadastro.php">Cadastrar usuário</a></header>
<div class="usuarios-grade"><?php foreach($usuarios as $u): ?><article class="usuario-card"><?= avatarUsuario($u) ?><h2><?= escapar($u['nome']) ?></h2><p><?= escapar($u['login']) ?> · <?= escapar(rotuloTipoUsuario($u['tipo'])) ?></p><span class="badge badge-<?= escapar($u['status']) ?>"><?= $u['status']==='ativo'?'Ativo':'Inativo' ?></span><p>Estoques: <?= $u['tipo']==='admin'?'Todos os estoques':escapar(implode(', ',array_map(fn($id)=>$estoques[$id]??'', $u['estoque_ids']))?:'Nenhum estoque') ?></p><?php if($u['administrador_principal']): ?><p class="texto-auxiliar">Administrador principal protegido.</p><?php if((int)$u['id']===(int)$_SESSION['usuario_id']): ?><a href="perfil.php">Minha conta</a><?php endif ?><?php else: ?><div class="acoes-card"><a href="editar.php?id=<?= (int)$u['id'] ?>">Editar</a><?php if($u['status']==='ativo'): ?><a href="excluir.php?id=<?= (int)$u['id'] ?>">Desativar</a><?php endif ?></div><?php endif ?></article><?php endforeach ?></div>
<?php require __DIR__.'/../partials/rodape.php'; ?>
