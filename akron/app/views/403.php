<?php
/**
 * app/views/403.php — Acesso negado.
 * Exibida por exigirAdmin() (includes/auth.php) quando um usuário comum tenta
 * abrir uma área exclusiva de administradores. O código HTTP 403 já foi
 * enviado por quem incluiu este arquivo.
 */

require_once __DIR__ . '/partials/icones.php';

$tituloPagina = 'Acesso negado';
require __DIR__ . '/partials/cabecalho.php';
?>

<header class="page-header"><div><h1>Acesso negado</h1><p>Esta área é exclusiva para administradores do sistema. Se você acredita que deveria ter acesso, fale com um administrador.</p></div><div class="actions"><?= linkVoltar('app/views/dashboard/painel.php', 'Voltar ao painel') ?></div></header>

<?php require __DIR__ . '/partials/rodape.php'; ?>
