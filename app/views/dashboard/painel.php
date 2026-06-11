<?php

session_start();

require_once '../../../includes/auth.php';

exigirLogin();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel</title>
</head>
<body>

    <h1>Painel</h1>

    <p>
        Nome do Usuário:
        <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
    </p>

    <p>
        Tipo:
        <?php echo htmlspecialchars($_SESSION['usuario_tipo']); ?>
    </p>

    <h1>Verifique como anda a produção da empresa</h1>
    <a href="/tcc/app/views/graficos/graficos_sensores.php">gráficos</a><br>
    <a href="/tcc/app/views/relatorios/relatorio_qualidade.php">Relatórios</a><br>
    <a href="/tcc/app/views/sensores/gestao_sensores.php">Sensores</a><br>

    <?php if ($_SESSION['usuario_tipo'] === 'admin'): ?>
        <a href="../usuarios/cadastro.php">Realizar Cadastro</a><br><br>
    <?php endif; ?>
    <?php if ($_SESSION['usuario_tipo'] === 'admin'): ?>
        <a href="../usuarios/editar.php">Editar Usuário</a><br><br>
    <?php endif; ?>
    <?php if ($_SESSION['usuario_tipo'] === 'admin'): ?>
        <a href="../usuarios/excluir.php">Excluir Cadastro</a><br><br>
    <?php endif; ?>



    <a href="/tcc/routes/logout.php">Logout</a><br>

</body>
</html>