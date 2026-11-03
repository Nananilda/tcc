<?php
require_once __DIR__ . '/../../../includes/auth.php'; exigirLogin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';
$controller = new SensorController($pdo, ehAdmin()); $erros=[];
$sensores = $controller->listarSensores($erros, $_GET);
$estoques = $pdo->query('SELECT id,nome FROM estoques e WHERE ' . escopoEstoque('e.id') . ' ORDER BY nome')->fetchAll();
$tituloPagina = 'Sensores'; $secaoTopbar = 'Sensores';
require __DIR__ . '/../partials/cabecalho.php';
?>
<h1>Sensores</h1><p class="pagina-subtitulo">Consulte os sensores dos estoques autorizados e sua comunicação real.</p>

<form method="get" class="card filtros-linha"><label for="filtro-estoque">Estoque</label><select id="filtro-estoque" name="estoque_id"><option value="">Todos os autorizados</option><?php foreach($estoques as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)($_GET['estoque_id']??0)===(int)$e['id']?'selected':'' ?>><?= escapar($e['nome']) ?></option><?php endforeach ?></select><button>Filtrar</button><?php if(ehAdmin()): ?><a class="btn-primario" href="editar_sensores.php?estoque_id=<?= (int)($_GET['estoque_id']??0) ?>">Cadastrar sensor</a><?php endif ?></form>
<?php foreach($erros as $erro): ?><p class="msg-erro" role="alert"><?= escapar($erro) ?></p><?php endforeach ?>
<div class="card tabela-rolagem"><?php if(!$sensores): ?><p class="msg-vazio">Nenhum sensor vinculado aos estoques selecionados.</p><?php else: ?>
<table><thead><tr><th>Sensor</th><th>Estoque</th><th>Tipo</th><th>Última leitura</th><th>Situação cadastral</th><th>Comunicação</th><th>Ações</th></tr></thead><tbody>
<?php foreach($sensores as $s): ?><tr><td><?= escapar($s['nome']) ?><br><small><?= escapar($s['localizacao']??'') ?></small></td><td><?= escapar($s['estoque_nome']) ?></td><td><?= escapar(SensorModel::rotulo($s['tipo'])) ?></td><td><?= $s['ultimo_valor']===null?'Sem leitura':escapar($s['ultimo_valor'].' '.SensorModel::unidade($s['tipo'])) ?><br><small><?= escapar(dataBr($s['ultima_leitura']??null)) ?></small></td><td><?= $s['status']==='ativo'?'Ativo':'Inativo' ?></td><td><span class="badge badge-<?= $s['comunicacao']==='online'?'ativo':'inativo' ?>"><?= escapar(['online'=>'Online','sem_comunicacao'=>'Sem comunicação','inativo'=>'Inativo'][$s['comunicacao']]) ?></span></td><td><a href="detalhes.php?id=<?= (int)$s['id'] ?>">Detalhes</a><?php if(ehAdmin()): ?> · <a href="editar_sensores.php?id=<?= (int)$s['id'] ?>">Editar</a> · <a href="excluir_sensores.php?id=<?= (int)$s['id'] ?>">Desativar</a><?php endif ?></td></tr><?php endforeach ?>
</tbody></table><?php endif ?></div>
<?php require __DIR__ . '/../partials/rodape.php'; ?>
