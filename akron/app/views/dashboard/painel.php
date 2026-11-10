<?php
/**
 * Painel inicial — central de navegação do ÁKRON.
 * Mostra os indicadores gerais (valores reais de DashboardController::obterResumo)
 * e os módulos em três grupos. Os gráficos e as leituras atuais ficam em
 * "Gráficos de sensores"; os alertas, em "Alertas".
 */
require_once __DIR__.'/../../../includes/auth.php'; exigirLogin();
require_once __DIR__.'/../../config/conexao.php';
require_once __DIR__.'/../../controllers/DashboardController.php';
require_once __DIR__.'/../../models/EstoqueModel.php';
require_once __DIR__.'/../partials/modulos.php';
$resumo=(new DashboardController($pdo))->obterResumo($_GET);
$estoqueId=max(0,(int)($_GET['estoque_id']??0));$estoques=(new EstoqueModel($pdo))->listarTodos();
$tituloPagina='Painel';$cssPagina=['painel.css'];$scriptsRodape=['painel.js'];
$primeiroNome=trim(explode(' ',trim((string)($_SESSION['usuario_nome']??'')))[0]??'');
$hora=(int)date('G');$saudacao=$hora<5?'Boa madrugada':($hora<12?'Bom dia':($hora<18?'Boa tarde':'Boa noite'));

/* Indicadores por importância: os dois primeiros ganham destaque. 'problema' = valor acima de zero pede atenção. */
$indicadores=[
    ['alertas_pendentes','Alertas ativos','Pendentes ou reconhecidos, ainda sem resolução','alerta',true,true],
    ['estoques_irregulares','Estoques com irregularidade','Condição ambiental em atenção ou crítica','alvo',true,true],
    ['estoques_comunicacao','Falhas de comunicação','Estoques com sensores sem comunicação','sinal-off',true,false],
    ['sensores_online','Sensores online','Enviando leituras dentro do prazo','sensor',false,false],
    ['estoques_total','Estoques cadastrados','Dentro do seu escopo de acesso','estoque',false,false],
];
$modulos=modulosAkron();$grupos=gruposModulos();$admin=ehAdmin();
$urlModulo=fn(array $m)=>urlApp('app/views/'.$m['rota']);
require __DIR__.'/../partials/cabecalho.php';
?>
<section class="painel-abertura" aria-labelledby="painel-titulo">
    <div class="painel-abertura-texto">
        <p class="painel-saudacao"><?= escapar($saudacao.($primeiroNome!==''?', '.$primeiroNome:'')) ?></p>
        <h1 id="painel-titulo">Painel do ÁKRON</h1>
        <p class="painel-resumo">Condições ambientais, comunicação e alertas que precisam de atenção.</p>
    </div>
    <form method="get" class="painel-filtro" aria-label="Escopo dos indicadores">
        <label for="painel-estoque">Estoque</label>
        <select name="estoque_id" id="painel-estoque" onchange="this.form.requestSubmit()">
            <option value="0">Todos os autorizados</option>
            <?php foreach($estoques as $e): ?><option value="<?= (int)$e['id'] ?>" <?= $estoqueId==(int)$e['id']?'selected':'' ?>><?= escapar($e['nome']) ?></option><?php endforeach ?>
        </select>
        <noscript><button type="submit">Aplicar</button></noscript>
    </form>
</section>

<p id="painel-status" class="painel-status" role="status" aria-live="polite"><?= escapar($resumo['erro']) ?></p>

<section aria-label="Indicadores gerais">
    <div class="indicadores">
        <?php foreach($indicadores as [$chave,$rotulo,$ajuda,$icone,$problema,$destaque]): $valor=(int)($resumo[$chave]??0);
            $tom=$problema?($valor>0?'ind-atencao':'ind-ok'):'ind-neutro'; ?>
        <article class="indicador <?= $destaque?'indicador-destaque':'' ?> <?= $tom ?>" data-chave="<?= $chave ?>" data-problema="<?= $problema?'1':'0' ?>">
            <span class="indicador-icone"><?= iconeSvg($icone) ?></span>
            <div class="indicador-corpo">
                <span class="indicador-valor" id="stat-<?= $chave ?>"><?= $valor ?></span>
                <span class="indicador-rotulo"><?= escapar($rotulo) ?></span>
                <span class="indicador-ajuda"><?= escapar($ajuda) ?></span>
            </div>
        </article>
        <?php endforeach ?>
    </div>
    <p class="painel-ultima"><?= iconeSvg('relogio') ?>Última leitura recebida: <strong id="painel-ultima"><?= escapar($resumo['ultima_atualizacao']?dataBr($resumo['ultima_atualizacao']):'Nenhuma leitura recebida') ?></strong></p>
</section>

<?php foreach($grupos as $chaveGrupo=>$grupo):
    $doGrupo=array_filter($modulos,fn($m)=>$m['grupo']===$chaveGrupo); if(!$doGrupo) continue; ?>
<section class="grupo-modulos grupo-<?= $chaveGrupo ?>" aria-labelledby="grupo-<?= $chaveGrupo ?>">
    <header class="grupo-cabecalho">
        <h2 id="grupo-<?= $chaveGrupo ?>"><?= escapar($grupo['titulo']) ?></h2>
        <p><?= escapar($grupo['descricao']) ?></p>
    </header>
    <div class="modulos-grade n-<?= count($doGrupo) ?>">
        <?php foreach($doGrupo as $chave=>$m):
            $descricao=($admin&&!empty($m['descricao_admin']))?$m['descricao_admin']:$m['descricao']; ?>
        <a class="modulo-card <?= escapar($m['cor']) ?>" href="<?= escapar($urlModulo($m)) ?>" style="--ordem:<?= (int)array_search($chave,array_keys($modulos)) ?>">
            <span class="modulo-icone"><?= iconeSvg($m['icone']) ?></span>
            <span class="modulo-texto">
                <span class="modulo-nome"><?= escapar($m['titulo']) ?></span>
                <span class="modulo-descricao"><?= escapar($descricao) ?></span>
            </span>
            <span class="modulo-seta" aria-hidden="true"><?= iconeSvg('seta') ?></span>
        </a>
        <?php endforeach ?>
    </div>
</section>
<?php if($chaveGrupo==='monitoramento'): ?>
<section class="card painel-recentes" aria-labelledby="alertas-recentes-titulo">
    <div class="painel-recentes-topo">
        <h2 id="alertas-recentes-titulo">Alertas recentes</h2>
        <a class="link-seta" href="<?= escapar(urlApp('app/views/graficos/alerta.php').'?estoque_id='.$estoqueId) ?>">Consultar alertas <?= iconeSvg('seta') ?></a>
    </div>
    <div id="painel-alertas"></div>
</section>
<?php endif ?>
<?php endforeach ?>


<script>window.AKRON_PAINEL={resumo:<?= jsonParaScript($resumo) ?>,url:<?= jsonParaScript(urlApp('routes/painel_dados.php').'?estoque_id='.$estoqueId) ?>};</script>
<?php require __DIR__.'/../partials/rodape.php'; ?>
