<?php
/**
 * gestao_usuarios.php
 * Menu de acesso às ações de gestão de usuários (somente administradores).
 */

session_start();
require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários — IndustrialOS</title>
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/usuarios.css">
</head>
<body>

    <div class="topbar">
        <div class="marca">IndustrialOS — Usuários</div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
            | Tipo: <strong>Administrador</strong>
        </div>
    </div>

    <div class="container">

        <h1>Gestão de Usuários</h1>
        <p class="usuarios-dica">Escolha uma das ações abaixo.</p>

        <div class="usuarios-menu-lista">
            <a class="menu-item" href="cadastro.php">
                <strong>Realizar Cadastro</strong><br>
                <span class="usuarios-dica">Criar um novo usuário no sistema.</span>
            </a>
            <a class="menu-item" href="editar.php">
                <strong>Editar Usuário</strong><br>
                <span class="usuarios-dica">Buscar e atualizar dados de um usuário existente.</span>
            </a>
            <a class="menu-item" href="excluir.php">
                <strong>Excluir Cadastro</strong><br>
                <span class="usuarios-dica">Remover permanentemente um usuário.</span>
            </a>
        </div>

        <div class="nav-rodape">
            <a href="../dashboard/painel.php">← Voltar ao painel</a>
        </div>

    </div>

</body>
</html>
