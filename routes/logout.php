<?php
/**
 * routes/logout.php
 * Encerra a sessão do usuário com segurança e volta para a tela de login.
 */

session_start();

require_once __DIR__ . '/../includes/auth.php';

encerrarSessao();

// Caminho relativo: funciona em qualquer subpasta de instalação
// (resolve em relação à URL atual, /routes/logout.php -> /index.php)
header('Location: ../index.php');
exit;
