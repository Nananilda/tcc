<?php
/** Navegação comum. O acesso real é sempre validado também no backend. */
require_once __DIR__ . '/icones.php';
require __DIR__ . '/head.php';
$nomeLogado = $_SESSION['usuario_nome'] ?? '';
$perfilLogado = rotuloTipoUsuario($_SESSION['usuario_tipo'] ?? null);
$caminhoAtual = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$itensMenu = [
    ['Painel', 'dashboard/painel.php', 'painel', '/dashboard/'],
    ['Estoques', 'estoques/listar.php', 'estoque', '/estoques/'],
    ['Sensores', 'sensores/listar_sensores.php', 'estoque', '/sensores/'],
    ['Monitoramento', 'graficos/graficos_sensores.php', 'grafico', '/graficos/graficos_sensores.php'],
    ['Alertas', 'graficos/alerta.php', 'alerta', '/graficos/alerta.php'],
    ['Relatórios', 'relatorios/relatorio_qualidade.php', 'relatorio', '/relatorios/'],
];
if (ehAdmin()) {
    $itensMenu[] = ['Usuários', 'usuarios/gestao_usuarios.php', 'usuarios', '/usuarios/gestao_usuarios.php'];
}
$itensMenu[] = ['Minha conta', 'usuarios/perfil.php', 'usuarios', '/usuarios/perfil.php'];
?>
<body class="app-shell">
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
    <header class="topbar">
        <a class="marca" href="<?php echo urlApp('app/views/dashboard/painel.php'); ?>"
           aria-label="<?php echo escapar(APP_NOME); ?> — ir para o painel">
            <span class="logo logo-simbolo marca-logo" aria-hidden="true"></span>
            <span class="marca-texto">
                <span class="logo logo-nome marca-nome" aria-hidden="true"></span>
                <span class="marca-secao">Monitoramento de estoques</span>
            </span>
        </a>
        <div class="acoes">
            <a class="avatar-link" href="<?= urlApp('app/views/usuarios/perfil.php') ?>"><?= avatarUsuario(['nome'=>$nomeLogado,'foto'=>$_SESSION['usuario_foto']??null]) ?></a>
            <div class="usuario-info">
                <a href="<?php echo urlApp('app/views/usuarios/perfil.php'); ?>"><strong><?php echo escapar($nomeLogado); ?></strong></a>
                <span class="usuario-perfil"><?php echo escapar($perfilLogado); ?></span>
            </div>
            <?php echo botaoTema(); ?>
            <a class="sair" href="<?php echo urlApp('routes/logout.php'); ?>">Sair</a>
        </div>
    </header>
    <details class="sidebar" id="navegacao-principal" open>
        <summary>Menu <span aria-hidden="true">☰</span></summary>
        <nav aria-label="Navegação principal">
            <p class="nav-grupo">AMBIENTE</p>
            <?php foreach ($itensMenu as [$rotuloMenu, $rotaMenu, $iconeMenu, $prefixoMenu]):
                $itemAtivo = str_contains($caminhoAtual, $prefixoMenu)

                    || ($rotuloMenu === 'Alertas' && str_contains($caminhoAtual, '/novo_alerta.php'));
            ?>
                <a class="nav-item<?php echo $itemAtivo ? ' ativo' : ''; ?>"
                   href="<?php echo urlApp('app/views/' . $rotaMenu); ?>"<?php echo $itemAtivo ? ' aria-current="page"' : ''; ?>>
                    <?php echo iconeSvg($iconeMenu); ?><span><?php echo escapar($rotuloMenu); ?></span>
                </a>
            <?php endforeach; ?>
            <p class="sidebar-assinatura">ÁKRON<br><span>Condições ambientais sob acompanhamento.</span></p>
        </nav>
    </details>
    <main class="container" id="conteudo-principal" tabindex="-1">
