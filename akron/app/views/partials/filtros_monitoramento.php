<?php
// Incluído por Monitoramento e Relatórios. Todos os seletores respeitam o escopo.
require_once __DIR__.'/../../../includes/estoques.php';
require_once __DIR__.'/../../models/SensorModel.php';
$estoquesFiltro=$pdo->query('SELECT e.id,e.nome FROM estoques e WHERE '.escopoEstoque('e.id').' ORDER BY e.nome')->fetchAll();
$sensoresFiltro=$pdo->query('SELECT s.id,s.nome,s.estoque_id, GROUP_CONCAT(DISTINCT h.estoque_id) AS estoques_historicos FROM sensores s LEFT JOIN leitura_sensor h ON h.sensor_id=s.id AND '.escopoEstoque('h.estoque_id').' WHERE '.escopoEstoque('s.estoque_id').' OR h.id IS NOT NULL GROUP BY s.id,s.nome,s.estoque_id ORDER BY s.nome')->fetchAll();
$filtros=$filtros??[];
foreach($filtros as $chave=>$valor) if(!is_scalar($valor)&&$valor!==null)$filtros[$chave]='';
?>
<form method="get" class="card" aria-label="Filtros">
<?php if(empty($filtroJanela)&&!empty($filtros['horas'])): ?><input type="hidden" name="horas" value="<?= (int)$filtros['horas'] ?>"><p>Período ativo: últimas <?= (int)$filtros['horas'] ?> horas. Informe datas para substituir essa janela.</p><?php endif ?>
<div class="monitor-filtros">
<label>Estoque<select name="estoque_id"><option value="0">Todos os autorizados</option><?php foreach($estoquesFiltro as $e):?><option value="<?= (int)$e['id'] ?>" <?= (int)($filtros['estoque_id']??0)===(int)$e['id']?'selected':'' ?>><?= escapar($e['nome']) ?></option><?php endforeach;?></select></label>
<label>Sensor<select name="sensor_id"><option value="0">Todos os sensores</option><?php foreach($sensoresFiltro as $s):?><option data-estoques="<?= escapar($s['estoque_id'].','.($s['estoques_historicos']??'')) ?>" value="<?= (int)$s['id'] ?>" <?= (int)($filtros['sensor_id']??0)===(int)$s['id']?'selected':'' ?>><?= escapar($s['nome']) ?></option><?php endforeach;?></select></label>
<label>Tipo de medição<select name="sensor"><option value="">Todos os tipos de medição</option><?php foreach(SensorModel::TIPOS as $tipo=>$meta):?><option value="<?= escapar($tipo) ?>" <?= ($filtros['sensor']??'')===$tipo?'selected':'' ?>><?= escapar($meta['label'].' ('.$meta['unidade'].')') ?></option><?php endforeach;?></select></label>
<label>Data inicial<input type="date" name="data_ini" value="<?= escapar($filtros['data_ini']??'') ?>"></label>
<label>Data final<input type="date" name="data_fim" value="<?= escapar($filtros['data_fim']??'') ?>"></label>
<?php if(!empty($filtroJanela)):?><label>Janela sem datas<select name="horas"><?php foreach(LeituraModel::ROTULOS_PERIODO as $h=>$label):?><option value="<?= $h ?>" <?= (int)($filtros['horas']??24)===$h?'selected':'' ?>><?= escapar($label) ?></option><?php endforeach;?></select></label><?php else:?>
<label>Valor mínimo<input type="number" step="any" name="val_min" value="<?= escapar($filtros['val_min']??'') ?>"></label><label>Valor máximo<input type="number" step="any" name="val_max" value="<?= escapar($filtros['val_max']??'') ?>"></label><?php endif;?>
<?php if(empty($filtroJanela)): ?><label>Registros por página<select name="por_pagina"><?php foreach([25,50,100,200] as $n): ?><option value="<?= $n ?>" <?= (int)($filtros['por_pagina']??25)===$n?'selected':'' ?>><?= $n ?></option><?php endforeach ?></select></label><?php endif ?></div><div class="acoes-formulario"><button type="submit">Aplicar filtros</button><a class="btn-secundario" href="<?= escapar(strtok($_SERVER['REQUEST_URI'],'?')) ?>">Limpar filtros</a></div>
</form>
