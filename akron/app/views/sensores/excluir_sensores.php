<?php
require_once __DIR__ . '/../../../includes/auth.php'; exigirAdmin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';
$controller = new SensorController($pdo, true); $erros=[];
/* Mesma tela para as duas ações: desativar (preserva o histórico) e excluir (definitivo, só sem histórico). */
$excluir = ($_POST['modo'] ?? $_GET['modo'] ?? '') === 'excluir';
$padraoVoltar = 'app/views/sensores/listar_sensores.php?modo=gestao';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!validarCSRF($_POST['csrf_token']??'')) $erros[]='Token de segurança inválido.';
    elseif ($excluir && ($_POST['confirmacao']??'')!=='excluir') $erros[]='Confirme a exclusão do sensor.';
    elseif (!$excluir && ($_POST['confirmacao']??'')!=='desativar') $erros[]='Confirme a desativação do sensor.';
    else {
        $r=$excluir ? $controller->executar('apagar',['sensor_id'=>$_POST['sensor_id']??0,'confirmacao'=>'excluir'])
                    : $controller->executar('toggle_status',['sensor_id'=>$_POST['sensor_id']??0,'novo_status'=>'inativo']);
        $erros=$r['erros'];
        /* Sucesso: volta à página de origem (sem os detalhes de um sensor que deixou de existir). */
        if (!$erros && $r['mensagem']!=='') redirecionarComMensagem(urlVoltar($padraoVoltar,$excluir),$r['mensagem']);
    }
}
$sensores=$controller->listarSensores($erros);
$selecionado=(int)($_POST['sensor_id']??$_GET['id']??0);
$tituloPagina=$excluir?'Excluir sensor':'Desativar sensor';
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1><?= escapar($tituloPagina) ?></h1><p><?= $excluir ? 'A exclusão é definitiva e só é possível para sensores sem leituras nem alertas. Para encerrar o uso preservando o histórico, use Desativar.' : 'Leituras e alertas serão preservados. Sensores inativos não recebem novas leituras.' ?></p></div></header>
<?= mensagensPagina('',$erros) ?>
<form method="post" class="card card-perigo" data-confirmar="<?= $excluir ? 'Excluir definitivamente este sensor? Esta ação não pode ser desfeita.' : 'Desativar o sensor selecionado? O recebimento de dados será interrompido. Todos os históricos serão preservados.' ?>"<?= $excluir ? ' data-confirmar-rotulo="Confirmar exclusão"' : '' ?>><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="modo" value="<?= $excluir?'excluir':'desativar' ?>"><?= campoVoltar() ?><label for="sensor-id">Sensor *</label><select id="sensor-id" name="sensor_id" required><?php foreach($sensores as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $selecionado===(int)$s['id']?'selected':'' ?>><?= escapar($s['nome'].' — '.$s['estoque_nome'].' ('.$s['status'].')') ?></option><?php endforeach ?></select><input type="hidden" name="confirmacao" value="<?= $excluir?'excluir':'desativar' ?>"><div class="form-acoes"><button type="submit" class="btn-perigo"><?= $excluir?'Excluir sensor':'Desativar sensor' ?></button><?= linkCancelar($padraoVoltar) ?></div></form>
<?php require __DIR__.'/../partials/rodape.php'; ?>
