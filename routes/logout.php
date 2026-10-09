<?php
/**
 * routes/logout.php
 * Encerra a sessão do usuário com segurança e volta para a tela de login
 * (que exibe o aviso "Você saiu do sistema").
 */

require_once __DIR__ . '/../includes/auth.php';

iniciarSessao();
encerrarSessao();

header('Location: ' . urlApp('index.php?saiu=1'));
exit;
