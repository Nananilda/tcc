<?php
require_once __DIR__.'/_bootstrap.php';exigirLoginApi();
require_once __DIR__.'/../app/controllers/ContaController.php';
$c=new ContaController($pdo,(int)$_SESSION['usuario_id']);
if ($_SERVER['REQUEST_METHOD']==='GET') jsonResponse(['sucesso'=>true,'usuario'=>$c->consultar()]);
if ($_SERVER['REQUEST_METHOD']==='POST') respostaDoControlador($c->salvar(corpoRequisicao(),$_FILES));
jsonResponse(['sucesso'=>false,'mensagem'=>'Método não permitido.'],405);
