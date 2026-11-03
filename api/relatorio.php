<?php
require_once __DIR__.'/_bootstrap.php';
exigirLoginApi();
if ($_SERVER['REQUEST_METHOD']!=='GET') jsonResponse(['sucesso'=>false,'mensagem'=>'Método não permitido.'],405);
require_once __DIR__.'/../app/controllers/RelatorioController.php';
$formato=$_GET['formato']??'';
if (!is_string($formato)||!in_array($formato,['','csv','pdf'],true)) jsonResponse(['sucesso'=>false,'mensagem'=>'Formato inválido.'],422);
$r=(new RelatorioController($pdo))->consultar($_GET,$formato!=='');
if ($r['erro']!=='') jsonResponse(['sucesso'=>false,'mensagem'=>$r['erro']],422);
if ($formato!=='') { require_once __DIR__.'/../app/services/ExportacaoRelatorio.php'; exportarRelatorio($r,$formato); }
jsonResponse(['sucesso'=>true]+$r);
