<?php
require_once __DIR__.'/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(verificarSessao()!==null){http_response_code(401);echo json_encode(['sucesso'=>false,'mensagem'=>'Sessão expirada.']);exit;}
require_once __DIR__.'/../app/config/conexao.php';
require_once __DIR__.'/../app/controllers/DashboardController.php';
$r=(new DashboardController($pdo))->obterResumo($_GET);
if($r['erro']!=='')http_response_code(422);
echo json_encode(['sucesso'=>$r['erro']==='','resumo'=>$r,'mensagem'=>$r['erro']],JSON_UNESCAPED_UNICODE);
