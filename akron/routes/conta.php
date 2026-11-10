<?php
require_once __DIR__.'/../includes/auth.php';
if (verificarSessao()!==null) { http_response_code(401);exit; }
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD']!=='POST' || !validarCSRF($_POST['csrf_token']??'')) {http_response_code(403);echo json_encode(['sucesso'=>false]);exit;}
require_once __DIR__.'/../app/controllers/ContaController.php';
$r=(new ContaController($pdo,(int)$_SESSION['usuario_id']))->salvar($_POST);
http_response_code($r['erros']?422:200);echo json_encode(['sucesso'=>!$r['erros']]+$r);
