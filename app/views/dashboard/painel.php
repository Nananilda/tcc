<?php

session_start();

require_once '../../../includes/auth.php';

exigirLogin();

require_once '../../config/conexao.php';
require_once '../../controllers/DashbordController.php';

$dashboard = new DashbordController($pdo);
$resumo    = $dashboard->obterResumo();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel</title>
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/painel.css">
</head>
<body>

    <div class="topbar">
        <div class="marca">IndustrialOS</div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
            | Tipo: <strong><?php echo htmlspecialchars($_SESSION['usuario_tipo']); ?></strong>
        </div>
    </div>

    <div class="container">

        <div class="painel-boas-vindas">
            <h1>Painel</h1>
            <p class="subtitulo">Verifique como anda a produção da empresa</p>
        </div>

        <div class="painel-stats">
            <div class="stat-card">
                <div class="stat-valor"><?php echo (int) $resumo['sensores_ativos']; ?> / <?php echo (int) $resumo['sensores_total']; ?></div>
                <div class="stat-label">Sensores ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-valor"><?php echo (int) $resumo['alertas_pendentes']; ?></div>
                <div class="stat-label">Alertas pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-valor"><?php echo htmlspecialchars($resumo['ultima_atualizacao']); ?></div>
                <div class="stat-label">Última atualização</div>
            </div>
        </div>

        <div class="painel-menu">
            <a class="menu-item" href="../graficos/graficos_sensores.php">
                <span class="icone">📈</span> Gráficos de Sensores
            </a>
            <a class="menu-item" href="../graficos/alerta.php">
                <span class="icone">⚠️</span> Alertas
            </a>
            <a class="menu-item" href="../../controllers/RelatorioController.php">
                <span class="icone">📄</span> Relatórios
            </a>
            <a class="menu-item" href="../sensores/gestao_sensores.php">
                <span class="icone">🛰️</span> Gestão de Sensores
            </a>
            <a class="menu-item" href="../sensores/listar_sensores.php">
                <span class="icone">📋</span> Consulta de Sensores
            </a>
            <?php if ($_SESSION['usuario_tipo'] === 'admin'): ?>
                <a class="menu-item" href="../usuarios/gestao_usuarios.php">
                    <span class="icone">👤</span> Gestão de Cadastro
                </a>
            <?php endif; ?>
        </div>

        <a class="painel-logout" href="http://localhost:8000/routes/logout.php">Logout</a>

    </div>

</body>
</html>
