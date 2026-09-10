<?php
/* Encerra a sessão do usuário com segurança e volta para a tela de login. */

session_start();

require_once __DIR__ . '/../includes/auth.php';

encerrarSessao();

// Caminho relativo: funciona em qualquer subpasta de instalação
header('Location: ../index.php');
exit;
