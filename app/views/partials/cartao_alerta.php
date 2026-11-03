<?php $unidade=SensorModel::unidade($a['sensor_tipo']??''); ?>
<article id="alerta-<?= (int)$a['id'] ?>" class="alerta-card <?= escapar($a['severidade']) ?>">
<div><span class="badge badge-<?= escapar($a['severidade']) ?>"><?= escapar(AlertaModel::rotuloSeveridade($a['severidade'])) ?></span><span class="badge badge-<?= escapar($a['status']) ?>"><?= escapar(AlertaModel::STATUS[$a['status']]) ?></span></div>
<h3><?= escapar($a['mensagem']) ?></h3><p><?= escapar($a['estoque_nome']??'') ?> · <?= escapar($a['sensor_nome']??'') ?></p><p><?= escapar(dataBr($a['criado_em'])) ?></p>
<p>Valor registrado: <strong><?= $a['valor']===null?'Não informado':escapar(AlertaModel::formatarLimite($a['valor'],$unidade)) ?></strong> · Limite ultrapassado: <?= $a['limite_ultrapassado']===null?'Não informado':escapar(AlertaModel::formatarLimite($a['limite_ultrapassado'],$unidade)) ?></p>
<details><summary>Detalhes da ocorrência #<?= (int)$a['id'] ?></summary>
<p>Origem: <?= $a['automatico']?'Automática':'Manual' ?></p>
<p>Reconhecimento: <?= $a['reconhecido_em']?escapar(dataBr($a['reconhecido_em']).' · '.($a['reconhecido_nome']??'Usuário indisponível')):'Ainda não reconhecido' ?></p>
<p>Normalização: <?= $a['normalizado_em']?escapar(dataBr($a['normalizado_em'])):'Não registrada' ?></p>
<p>Resolução: <?= $a['resolvido_em']?escapar(dataBr($a['resolvido_em']).' · '.($a['resolvido_nome']??'Usuário indisponível')):'Não registrada' ?></p>
<?php if($a['automatico']&&$a['resolvido']&&!$a['normalizado_em']): ?><p class="msg-alerta">Atendimento concluído; condição ambiental ainda não normalizada.</p><?php endif ?>
<p>Observações: <?= $a['observacoes']?nl2br(escapar($a['observacoes'])):'Nenhuma observação registrada.' ?></p>
<?php if(!empty($permitirAcoes)&&ehAdmin()&&!$a['resolvido']): ?><div class="acoes-alerta">
<?php if($a['status']==='pendente'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="alerta_id" value="<?= (int)$a['id'] ?>"><button class="btn-secundario" name="acao" value="reconhecer">Reconhecer</button></form><?php endif ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>"><input type="hidden" name="alerta_id" value="<?= (int)$a['id'] ?>"><label for="obs-<?= (int)$a['id'] ?>">Observações (opcional)</label><textarea id="obs-<?= (int)$a['id'] ?>" name="observacoes" maxlength="1000" rows="2" placeholder="Registre informações adicionais, se necessário"><?= (int)($_POST['alerta_id']??0)===(int)$a['id']?escapar($_POST['observacoes']??''):'' ?></textarea><button name="acao" value="resolver">Resolver alerta</button></form>
</div><?php endif ?>
</details></article>
