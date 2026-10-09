<?php
/**
 * app/views/usuarios/gestao_usuarios.php — Gestão de Usuários.
 * Menu de acesso às ações de gestão de usuários (somente administradores):
 * cadastrar, editar e excluir.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../partials/icones.php';

// Ações do menu: [ícone, título, descrição, página]
$acoes = [
    ['cadastrar', 'Realizar Cadastro', 'Criar um novo usuário no sistema.', 'cadastro.php'],
    ['editar', 'Editar Usuário', 'Buscar e atualizar dados de um usuário existente.', 'editar.php'],
    ['excluir', 'Excluir Cadastro', 'Remover permanentemente um usuário.', 'excluir.php'],
];

$tituloPagina = 'Gestão de Usuários';
$secaoTopbar = 'Usuários';
$cssPagina = ['usuarios.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Gestão de Usuários</h1>
<p class="usuarios-dica">Escolha uma das ações abaixo.</p>

<nav class="usuarios-menu-lista" aria-label="Ações de gestão de usuários">
    <?php foreach ($acoes as [$icone, $titulo, $descricao, $pagina]): ?>
        <a class="menu-item" href="<?php echo escapar($pagina); ?>">
            <span class="icone"><?php echo iconeSvg($icone); ?></span>
            <span>
                <strong><?php echo escapar($titulo); ?></strong><br>
                <span class="usuarios-dica"><?php echo escapar($descricao); ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</nav>

<div class="nav-rodape">
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
