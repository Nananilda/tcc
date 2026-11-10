<?php
require_once __DIR__ . '/../../../includes/auth.php'; exigirLogin();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';
$controller = new SensorController($pdo, ehAdmin()); $erros=[];
$sensores = $controller->listarSensores($erros, $_GET);
$estoques = $pdo->query('SELECT id,nome FROM estoques e WHERE ' . escopoEstoque('e.id') . ' ORDER BY nome')->fetchAll();
/* Dois modos da mesma listagem: consulta (leituras e comunicação, para todos) e gestão
   (cadastro e edição, somente administrador). O backend continua exigindo admin nas ações. */
$cssPagina = ['sensores.css'];
$gestao = ehAdmin() && (($_GET['modo'] ?? '') === 'gestao' || basename($_SERVER['SCRIPT_NAME'] ?? '') === 'gestao_sensores.php');
$moduloAtual = $gestao ? 'sensores' : 'consulta';
$tituloPagina = $gestao ? 'Gestão de sensores' : 'Consulta de sensores';

require __DIR__ . '/../partials/cabecalho.php';
?>
<header class="page-header"><div><h1><?= escapar($tituloPagina) ?></h1><p><?= $gestao ? 'Cadastre, edite e desative sensores. A situação cadastral é independente da comunicação.' : 'Consulte a última leitura e a comunicação real de cada sensor dos estoques autorizados.' ?></p></div><?php if($gestao): ?><a class="btn-primario" href="editar_sensores.php?estoque_id=<?= (int)($_GET['estoque_id']??0) ?>">Cadastrar sensor</a><?php endif ?></header>

<form method="get" class="card filtros-linha"><?php if($gestao): ?><input type="hidden" name="modo" value="gestao"><?php endif ?><label for="filtro-estoque">Estoque</label><select id="filtro-estoque" name="estoque_id"><option value="">Todos os autorizados</option><?php foreach($estoques as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)($_GET['estoque_id']??0)===(int)$e['id']?'selected':'' ?>><?= escapar($e['nome']) ?></option><?php endforeach ?></select><button>Filtrar</button></form>
<?php foreach($erros as $erro): ?><p class="msg-erro" role="alert"><?= escapar($erro) ?></p><?php endforeach ?>
<div class="card tabela-rolagem"><?php if(!$sensores): ?><p class="msg-vazio">Nenhum sensor vinculado aos estoques selecionados.</p><?php else: ?>
<table><thead><tr><th>Sensor</th><th>Estoque</th><th>Tipo</th><th>Última leitura</th><th>Situação cadastral</th><th>Comunicação</th><th>Ações</th></tr></thead><tbody>
<?php foreach($sensores as $s): $ativo=$s['status']==='ativo'; ?><tr><td><strong><?= escapar($s['nome']) ?></strong><br><small><?= escapar($s['localizacao']??'') ?></small></td><td><?= escapar($s['estoque_nome']) ?></td><td><?= escapar(SensorModel::rotulo($s['tipo'])) ?></td><td><?= $s['ultimo_valor']===null?'Sem leitura':'<strong>'.escapar($s['ultimo_valor'].' '.SensorModel::unidade($s['tipo'])).'</strong>' ?><br><small><?= escapar(dataBr($s['ultima_leitura']??null)) ?></small><?php if($s['ultimo_valor']!==null && $ativo && $s['comunicacao']!=='online'): ?><br><small class="leitura-defasada">Leitura desatualizada</small><?php endif ?></td><td><span class="badge badge-<?= $ativo?'ativo':'inativo' ?>"><?= $ativo?'Ativo':'Inativo' ?></span></td><td><span class="badge badge-<?= escapar($s['comunicacao']) ?>"><?= escapar(['online'=>'Online','sem_comunicacao'=>'Sem comunicação','inativo'=>'Inativo'][$s['comunicacao']]) ?></span></td><td><div class="acoes-tabela"><a href="detalhes.php?id=<?= (int)$s['id'] ?>">Detalhes</a><?php if($gestao): ?><a href="editar_sensores.php?id=<?= (int)$s['id'] ?>">Editar</a><a href="excluir_sensores.php?id=<?= (int)$s['id'] ?>">Desativar</a><?php endif ?></div></td></tr><?php endforeach ?>
</tbody></table><?php endif ?></div>
<?php require __DIR__ . '/../partials/rodape.php'; ?>
