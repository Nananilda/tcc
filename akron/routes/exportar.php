<?php
require_once __DIR__.'/../includes/auth.php';exigirLogin();
require_once __DIR__.'/../app/services/HistoricoEntidade.php';
require_once __DIR__.'/../app/models/EstoqueModel.php';
require_once __DIR__.'/../app/controllers/RelatorioController.php';
require_once __DIR__.'/../app/services/ExportacaoDetalhes.php';
try {
    $formato=$_GET['formato']??'csv';$entidade=$_GET['entidade']??'';
    if(!in_array($formato,['csv','pdf'],true))throw new InvalidArgumentException('Formato inválido.');
    if($entidade==='alertas'){
        $f=AlertaModel::validarFiltros($_GET);$alertas=(new AlertaModel($pdo))->listarRecentes(50,$f,1,true);
        $rotulos=['estoque_id'=>'Estoque (ID)','sensor_id'=>'Sensor (ID)','severidade'=>'Gravidade','status'=>'Situação','data_inicial'=>'Data inicial','data_final'=>'Data final'];$identificacao=['Consulta'=>'Alertas dos estoques autorizados'];
        foreach($f as $k=>$v)$identificacao[$rotulos[$k]??$k]=str_starts_with($k,'data_')?dataBr($v,false):$v;
        exportarDetalhes(['titulo'=>'Histórico de alertas','alertas'=>$alertas,'identificacao'=>$identificacao],$formato,'alertas');
    }
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id||!in_array($entidade,['sensor','estoque'],true))throw new InvalidArgumentException('Registro inválido.');
    $sensores=[];$identificacao=[];
    if($entidade==='estoque'){
        exigirEstoque($pdo,$id);$e=(new EstoqueModel($pdo))->buscarPorId($id);
        $identificacao=['Estoque'=>$e['nome'],'Localização'=>$e['localizacao'],'Descrição'=>$e['descricao'],'Situação cadastral'=>ucfirst($e['status'])];
        foreach((new EstoqueModel($pdo))->listarTodos() as $res)if((int)$res['id']===$id)$identificacao['Condição ambiental']=$res['condicao'];
        $sensores=(new SensorModel($pdo))->listarTodos(['estoque_id'=>$id]);$escopo=['estoque_id'=>$id];
    }else{
        $s=(new SensorModel($pdo))->buscarPorId($id);if(!$s){http_response_code(403);exit('Sensor indisponível ou sem permissão.');}
        $identificacao=['Sensor'=>$s['nome'],'Estoque'=>$s['estoque_nome'],'Localização'=>$s['localizacao'],'Situação cadastral'=>ucfirst($s['status']),'Comunicação'=>$s['comunicacao']];$sensores=[$s];$escopo=['sensor_id'=>$id,'sensor'=>$s['tipo']];
    }
    $f=(new HistoricoEntidade($pdo))->filtros(array_replace($_GET,$escopo));
    $r=(new RelatorioController($pdo))->consultar($f,true);if($r['erro'])throw new InvalidArgumentException($r['erro']);
    $r['alertas']=(new AlertaModel($pdo))->listarRecentes(50,array_filter(['estoque_id'=>$f['estoque_id'],'sensor_id'=>$f['sensor_id'],'data_inicial'=>$f['data_ini'],'data_final'=>$f['data_fim']]),1,true);
    $r['identificacao']=$identificacao;$r['sensores']=$sensores;
    exportarDetalhes($r,$formato,$entidade.'-'.$id);
} catch(InvalidArgumentException $e){http_response_code(422);$tituloPagina='Não foi possível exportar';require __DIR__.'/../app/views/partials/cabecalho.php';echo '<h1>Não foi possível exportar</h1><p class="msg-erro">'.escapar($e->getMessage()).'</p>';require __DIR__.'/../app/views/partials/rodape.php';}
