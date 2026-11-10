<?php
require_once __DIR__ . '/../../../includes/auth.php'; exigirAdmin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';
$controller = new SensorController($pdo, true); $erros=[]; $mensagem='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!validarCSRF($_POST['csrf_token']??'')) $erros[]='Token de segurança inválido.';
    elseif (($_POST['confirmacao']??'')!=='desativar') $erros[]='Digite desativar para confirmar.';
    else { $r=$controller->executar('toggle_status',['sensor_id'=>$_POST['sensor_id']??0,'novo_status'=>'inativo']); $erros=$r['erros']; $mensagem=$r['mensagem']; }
}
$sensores=$controller->listarSensores($erros); $tituloPagina='Desativar sensor';
require __DIR__.'/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1>Desativar sensor</h1><p>Leituras e alertas serão preservados. Sensores inativos não recebem novas leituras.</p></div></header>
<?php foreach($erros as $erro): ?><p class="msg-erro" role="alert"><?= escapar($erro) ?></p><?php endforeach ?>
<?php if($mensagem): ?><p class="msg-sucesso" role="status"><?= escapar($mensagem) ?></p><?php endif ?>
<form method="post" class="card" data-confirmar="Desativar o registro selecionado? O acesso ou recebimento de dados será interrompido. Todos os históricos serão preservados."><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><label for="sensor-id">Sensor *</label><select id="sensor-id" name="sensor_id" required><?php foreach($sensores as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($_GET['id']??$_POST['sensor_id']??0)===(int)$s['id']?'selected':'' ?>><?= escapar($s['nome'].' — '.$s['estoque_nome'].' ('.$s['status'].')') ?></option><?php endforeach ?></select><input type="hidden" name="confirmacao" value="desativar"><div class="form-acoes"><button>Desativar sensor</button><a class="btn-secundario" href="listar_sensores.php">Cancelar</a></div></form>
<?php require __DIR__.'/../partials/rodape.php'; ?>
