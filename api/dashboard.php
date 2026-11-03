<?php
require_once __DIR__.'/_bootstrap.php';
exigirLoginApi();
if($_SERVER['REQUEST_METHOD']!=='GET')jsonResponse(['sucesso'=>false,'mensagem'=>'Método não permitido.'],405);
require_once __DIR__.'/../app/controllers/DashboardController.php';
$r=(new DashboardController($pdo))->obterResumo($_GET);
jsonResponse(['sucesso'=>$r['erro']==='','resumo'=>$r,'mensagem'=>$r['erro']],$r['erro']===''?200:422);
