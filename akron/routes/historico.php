<?php
require_once __DIR__.'/../includes/auth.php';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(verificarSessao()!==null){http_response_code(401);echo json_encode(['mensagem'=>'Sessão expirada. Entre novamente.']);exit;}
require_once __DIR__.'/../app/services/HistoricoEntidade.php';
try {
    $tipo=$_GET['tipo_lista']??'';if(!in_array($tipo,['alertas','leituras'],true))throw new InvalidArgumentException('Histórico inválido.');
    $offset=filter_var($_GET['offset']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000000]]);
    if($offset===false)throw new InvalidArgumentException('Página inválida.');
    $h=new HistoricoEntidade($pdo);$f=$h->filtros($_GET);$r=$h->consultar($f,$tipo,$offset,$tipo==='alertas'?10:25);
    ob_start();foreach($r['registros'] as $registro){if($tipo==='alertas'){$a=$registro;$permitirAcoes=false;require __DIR__.'/../app/views/partials/cartao_alerta.php';}else{$l=$registro;require __DIR__.'/../app/views/partials/linha_leitura.php';}}$html=ob_get_clean();
    echo json_encode(['html'=>$html,'quantidade'=>count($r['registros']),'total'=>$r['total']],JSON_UNESCAPED_UNICODE);
} catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['mensagem'=>$e->getMessage()]);}
