<?php
/**
 * app/views/partials/cabecalho.php
 * Cabeçalho das páginas INTERNAS (usuário logado): <head> + topbar com a
 * marca ÁKRON, o usuário logado, o botão de tema e o link "Sair", e a
 * abertura do <main class="container">. Deve ser fechado por rodape.php.
 *
 * Variáveis definidas pela página antes do require:
 *   $tituloPagina, $cssPagina, $scriptsHead  → repassadas a head.php
 *   $secaoTopbar  string  Nome da seção exibido sob a marca (ex.: 'Gráficos'). Opcional.
 *
 * Uso típico em uma view:
 *     $tituloPagina = 'Alertas';
 *     $secaoTopbar  = 'Alertas';
 *     $cssPagina    = ['graficos.css'];
 *     require __DIR__ . '/../partials/cabecalho.php';
 */

require_once __DIR__ . '/icones.php';
require __DIR__ . '/head.php';

$secaoTopbar = $secaoTopbar ?? '';
$nomeLogado = $_SESSION['usuario_nome'] ?? '';
$perfilLogado = rotuloTipoUsuario($_SESSION['usuario_tipo'] ?? null);
?>

<body>

    <header class="topbar">
        <a class="marca" href="<?php echo urlApp('app/views/dashboard/painel.php'); ?>"
            aria-label="<?php echo escapar(APP_NOME); ?> — ir para o painel">
            <span class="logo logo-simbolo marca-logo" aria-hidden="true"></span>
            <span class="marca-texto">
                <span class="logo logo-nome marca-nome" aria-hidden="true"></span>
                <?php if ($secaoTopbar !== ''): ?>
                    <span class="marca-secao"><?php echo escapar($secaoTopbar); ?></span>
                <?php endif; ?>
            </span>
        </a>

        <div class="acoes">
            <div class="usuario-info">
                <span class="rotulo">Usuário:</span> <strong><?php echo escapar($nomeLogado); ?></strong>
                <span class="usuario-perfil"><?php echo escapar($perfilLogado); ?></span>
            </div>
            <?php echo botaoTema(); ?>
            <a class="sair" href="<?php echo urlApp('routes/logout.php'); ?>">Sair</a>
        </div>
    </header>

    <main class="container">
