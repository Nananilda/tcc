<?php
require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';
$controller = new SensorController($pdo, true);
$resultado = ['mensagem' => '', 'erros' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') $resultado = $controller->processarRequisicao();
$id = (int) ($_POST['sensor_id'] ?? $_GET['id'] ?? 0);
$sensor = $id ? $controller->buscarSensor($id) : null;
if ($id && !$sensor) { http_response_code(404); $resultado['erros'][] = 'Sensor não encontrado.'; }
$dados = $sensor ?: ['nome'=>'','tipo'=>'temperatura','estoque_id'=>$_GET['estoque_id'] ?? '', 'localizacao'=>'','status'=>'ativo','intervalo_comunicacao'=>300];
if ($resultado['erros'] && $_SERVER['REQUEST_METHOD'] === 'POST') $dados = array_merge($dados, $_POST);
$estoques = $pdo->query('SELECT id,nome,status FROM estoques ORDER BY nome')->fetchAll();
$tituloPagina = $id ? 'Editar sensor' : 'Cadastrar sensor';
$secaoTopbar = 'Sensores';
$cssPagina = ['sensores.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>
<h1><?= escapar($tituloPagina) ?></h1>
<p class="pagina-subtitulo">Configure o sensor e os limites adequados aos materiais armazenados. Campos com * são obrigatórios.</p>
<?php if ($resultado['mensagem']): ?><div class="msg-sucesso" role="status"><?= escapar($resultado['mensagem']) ?></div><?php endif ?>
<?php if ($resultado['erros']): ?><div class="msg-erro" role="alert"><ul><?php foreach ($resultado['erros'] as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php if (!$id || $sensor): ?>
<form method="post" class="card">
<input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>">
<input type="hidden" name="acao" value="<?= $id ? 'atualizar' : 'cadastrar' ?>">
<input type="hidden" name="sensor_id" value="<?= $id ?>">
<fieldset><legend>Identificação</legend><div class="sensores-form-novo">
<div><label for="sensor-nome">Nome *</label><input id="sensor-nome" name="nome" required minlength="3" maxlength="100" value="<?= escapar($dados['nome']) ?>"></div>
<div><label for="sensor-tipo">Tipo *</label><select id="sensor-tipo" name="tipo" required><?php foreach (SensorModel::TIPOS as $tipo => $meta): ?><option value="<?= escapar($tipo) ?>" <?= $dados['tipo'] === $tipo ? 'selected' : '' ?>><?= escapar(SensorModel::rotuloComUnidade($tipo)) ?></option><?php endforeach ?></select></div>
</div></fieldset>
<fieldset><legend>Localização e estoque</legend><div class="sensores-form-novo">
<div><label for="sensor-estoque">Estoque *</label><select name="estoque_id" id="sensor-estoque" required><option value="">Selecione um estoque</option><?php foreach ($estoques as $estoque): ?><option value="<?= (int)$estoque['id'] ?>" <?= (int)$dados['estoque_id']===(int)$estoque['id'] ? 'selected' : '' ?>><?= escapar($estoque['nome'] . ($estoque['status'] === 'inativo' ? ' (Inativo)' : '')) ?></option><?php endforeach ?></select></div>
<div><label for="sensor-localizacao">Localização dentro do estoque</label><input name="localizacao" id="sensor-localizacao" maxlength="150" value="<?= escapar($dados['localizacao'] ?? '') ?>" placeholder="Ex.: prateleira 3, corredor B"></div>
</div><p class="texto-auxiliar">A transferência preserva o estoque original das leituras e alertas anteriores.</p></fieldset>
<fieldset><legend>Limites ambientais</legend><p>Informe os limites na unidade do tipo escolhido. Campo vazio desativa aquele limite. Valores iguais ao limite permanecem na faixa permitida.</p><div class="sensores-form-novo">
<?php foreach (['limite_critico_min'=>'Crítico inferior','limite_atencao_min'=>'Atenção inferior','limite_atencao_max'=>'Atenção superior','limite_critico_max'=>'Crítico superior'] as $campo=>$rotulo): ?>
<div><label for="<?= escapar(str_replace('_','-',$campo)) ?>"><?= escapar($rotulo) ?></label><input type="number" step="0.01" min="-99999999.99" max="99999999.99" id="<?= escapar(str_replace('_','-',$campo)) ?>" name="<?= escapar($campo) ?>" value="<?= escapar($dados[$campo] ?? '') ?>"></div>
<?php endforeach ?></div></fieldset>
<fieldset><legend>Status e comunicação</legend><div class="sensores-form-novo">
<div><label for="sensor-status">Status *</label><select name="status" id="sensor-status"><option value="ativo" <?= $dados['status']==='ativo'?'selected':'' ?>>Ativo</option><option value="inativo" <?= $dados['status']==='inativo'?'selected':'' ?>>Inativo</option></select></div>
<div><label for="sensor-intervalo">Prazo sem leitura para perder comunicação (segundos) *</label><input type="number" name="intervalo_comunicacao" id="sensor-intervalo" min="1" max="86400" required value="<?= (int) $dados['intervalo_comunicacao'] ?>"></div>
</div></fieldset>
<div class="form-acoes"><button type="submit">Salvar sensor</button><a class="btn-secundario" href="listar_sensores.php">Cancelar</a></div>
</form><?php endif ?>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
