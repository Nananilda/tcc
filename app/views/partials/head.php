<?php
/**
 * app/views/partials/head.php
 * Abre o documento HTML e monta o <head> comum a TODAS as páginas do sistema:
 * codificação, título padronizado, favicons, script de tema e folhas de estilo.
 *
 * Variáveis que a página define ANTES de incluir este arquivo (todas opcionais):
 *   $tituloPagina  string  Título da aba, sem o nome do sistema. Ex.: 'Painel'
 *                          (vira "Painel — ÁKRON"). Sem ele: "ÁKRON — Sistema de ...".
 *   $cssPagina     array   CSS específicos da página (arquivos de public/assets/css).
 *   $scriptsHead   array   Scripts extras no <head> (caminhos relativos a public/assets/js).
 *
 * Requer includes/auth.php ou app/config/conexao.php já carregados (urlApp, escapar).
 * O <body>, a topbar e o fechamento ficam em cabecalho.php e rodape.php.
 */

$tituloCompleto = !empty($tituloPagina)
    ? $tituloPagina . ' — ' . APP_NOME
    : APP_NOME . ' — ' . APP_DESCRICAO;
$cssPagina = $cssPagina ?? [];
$scriptsHead = $scriptsHead ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapar($tituloCompleto); ?></title>

    <!-- Ícones do navegador: SVG (moderno) com fallbacks em ICO/PNG -->
    <link rel="icon" href="<?php echo urlApp('public/assets/img/favicon.ico'); ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?php echo urlApp('public/assets/img/favicon.svg'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo urlApp('public/assets/img/favicon-32.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo urlApp('public/assets/img/apple-touch-icon.png'); ?>">

    <!-- Tema claro/escuro: carregado SEM defer para aplicar o tema antes da primeira pintura -->
    <script src="<?php echo urlApp('public/assets/js/tema.js'); ?>"></script>

    <link rel="stylesheet" href="<?php echo urlApp('public/assets/css/style.css'); ?>">
    <?php foreach ($cssPagina as $css): ?>
        <link rel="stylesheet" href="<?php echo urlApp('public/assets/css/' . $css); ?>">
    <?php endforeach; ?>
    <?php foreach ($scriptsHead as $script): ?>
        <script src="<?php echo urlApp('public/assets/js/' . $script); ?>"></script>
    <?php endforeach; ?>
</head>
