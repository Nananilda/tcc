<?php
/**
 * Cabeçalho comum das páginas internas (sem menu lateral).
 * Mostra: marca, trilha de orientação (Painel › Grupo › Página), usuário e Sair.
 * O acesso real é sempre validado também no backend.
 *
 * Variáveis opcionais da página: $tituloPagina (nome exibido) e $moduloAtual
 * (chave de modulos.php; sem ela, o módulo é deduzido pelo caminho do script).
 */
require_once __DIR__ . '/icones.php';
require_once __DIR__ . '/modulos.php';
require __DIR__ . '/head.php';
$nomeLogado = $_SESSION['usuario_nome'] ?? '';
$perfilLogado = rotuloTipoUsuario($_SESSION['usuario_tipo'] ?? null);
$caminhoAtual = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$ehPainel = str_contains($caminhoAtual, '/dashboard/painel.php');
$todosModulos = modulosAkron(true);
$chaveModulo = $moduloAtual ?? moduloPorCaminho($caminhoAtual);
$modulo = $todosModulos[$chaveModulo] ?? null;
$grupoModulo = $modulo ? (gruposModulos()[$modulo['grupo']]['titulo'] ?? '') : '';
$nomePagina = $ehPainel ? 'Painel' : (!empty($tituloPagina) ? $tituloPagina : ($modulo['titulo'] ?? ''));
$urlPainel = urlApp('app/views/dashboard/painel.php');
?>
<body class="app-shell<?php echo $modulo ? ' ' . escapar($modulo['cor']) : ''; ?>" data-modulo="<?php echo escapar($chaveModulo ?? ''); ?>">
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
    <header class="topbar">
        <div class="topbar-inicio">
            <a class="marca" href="<?php echo $urlPainel; ?>"
               aria-label="<?php echo escapar(APP_NOME); ?> — ir para o painel">
                <span class="logo logo-simbolo marca-logo" aria-hidden="true"></span>
                <span class="logo logo-nome marca-nome" aria-hidden="true"></span>
            </a>
            <?php if (!$ehPainel): ?>
                <nav class="trilha" aria-label="Onde você está">
                    <a class="trilha-painel" href="<?php echo $urlPainel; ?>">
                        <?php echo iconeSvg('voltar'); ?><span>Painel</span>
                    </a>
                    <?php if ($grupoModulo): ?>
                        <span class="trilha-sep" aria-hidden="true">/</span>
                        <span class="trilha-grupo"><?php echo escapar($grupoModulo); ?></span>
                    <?php endif; ?>
                    <?php if ($nomePagina): ?>
                        <span class="trilha-sep" aria-hidden="true">/</span>
                        <span class="trilha-atual" aria-current="page"><?php echo escapar($nomePagina); ?></span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
        <div class="acoes">
            <a class="conta-link" href="<?php echo urlApp('app/views/usuarios/perfil.php'); ?>"
               title="Configurações pessoais" aria-label="Configurações pessoais de <?php echo escapar($nomeLogado); ?>">
                <?php echo avatarUsuario(['nome' => $nomeLogado, 'foto' => $_SESSION['usuario_foto'] ?? null]); ?>
                <span class="usuario-info">
                    <strong><?php echo escapar($nomeLogado); ?></strong>
                    <span class="usuario-perfil"><?php echo escapar($perfilLogado); ?></span>
                </span>
            </a>
            <a class="config-link<?php echo $chaveModulo === 'pessoal' ? ' ativo' : ''; ?>" href="<?php echo urlApp('app/views/usuarios/perfil.php'); ?>"
               title="Configurações pessoais" aria-label="Configurações pessoais"<?php echo $chaveModulo === 'pessoal' ? ' aria-current="page"' : ''; ?>>
                <?php echo iconeSvg('config'); ?>
            </a>
            <a class="sair" href="<?php echo urlApp('routes/logout.php'); ?>" title="Encerrar sessão">
                <?php echo iconeSvg('sair'); ?><span>Sair</span>
            </a>
        </div>
    </header>
    <?php if ($modulo): ?>
        <template id="icone-modulo"><?php echo iconeSvg($modulo['icone']); ?></template>
    <?php endif; ?>
    <main class="container" id="conteudo-principal" tabindex="-1">
