<?php
/**
 * app/views/graficos/alerta.php — Alertas dos sensores.
 *
 * Mostra, por severidade, quantos alertas estão pendentes (cartões + gráfico
 * de barras) e o histórico recente. Administradores podem marcar alertas como
 * resolvidos (formulário POST protegido por token CSRF).
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/AlertaController.php';
require_once __DIR__ . '/../../models/SensorModel.php';

$ehAdmin = ehAdmin();
$controller = new AlertaController($pdo, $ehAdmin);

// Se for um POST ("Marcar resolvido"), executa a ação antes de consultar os dados
['mensagem' => $mensagem, 'erros' => $erros] = $controller->processarRequisicao();

$alertas = $controller->listarAlertas();
$contagem = $controller->contarPorSeveridade();
$csrf_token = gerarCSRF();

$tituloPagina = 'Alertas';
$secaoTopbar = 'Alertas';
$cssPagina = ['graficos.css'];
$scriptsHead = ['vendor/chart.umd.js', 'graficos.js'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Alertas dos Sensores</h1>

<?php if ($mensagem): ?>
    <div class="msg-sucesso" role="status"><?php echo escapar($mensagem); ?></div>
<?php endif; ?>

<?php if (!empty($erros)): ?>
    <div class="msg-erro" role="alert">
        <ul>
            <?php foreach ($erros as $erro): ?>
                <li><?php echo escapar($erro); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ── Cartões de resumo por severidade (somente alertas pendentes) ─────── -->
<div class="alertas-cards">
    <div class="card-severidade critico">
        <div class="valor"><?php echo (int) $contagem['critico']; ?></div>
        <div>Críticos</div>
    </div>
    <div class="card-severidade atencao">
        <div class="valor"><?php echo (int) $contagem['atencao']; ?></div>
        <div>Atenção</div>
    </div>
    <div class="card-severidade info">
        <div class="valor"><?php echo (int) $contagem['info']; ?></div>
        <div>Informativos</div>
    </div>
</div>

<!-- ── Gráfico de barras (contagem de pendentes por severidade) ─────────── -->
<div class="card grafico-wrapper">
    <div class="grafico-area grafico-area-baixa"><canvas id="graficoAlertas"></canvas></div>
</div>
<script>
    criarGraficoBarras(
        'graficoAlertas',
        ['Crítico', 'Atenção', 'Informativo'],
        <?php echo jsonParaScript([(int) $contagem['critico'], (int) $contagem['atencao'], (int) $contagem['info']]); ?>,
        'Alertas pendentes',
        'Alertas pendentes por severidade',
        ['critico', 'atencao', 'info'] // chaves da paleta do tema (ver graficos.js)
    );
</script>

<!-- ── Histórico recente ─────────────────────────────────────────────────── -->
<div class="card">
    <h2>Histórico recente</h2>

    <?php if (empty($alertas)): ?>
        <p class="msg-vazio">Nenhum alerta registrado.</p>
    <?php else: ?>
        <ul class="lista-alertas">
            <?php foreach ($alertas as $alerta): ?>
                <?php $resolvido = (int) $alerta['resolvido'] === 1; ?>
                <li class="<?php echo $resolvido ? 'alerta-resolvido' : ''; ?>">
                    <div>
                        <div class="alerta-titulo">
                            <span class="badge badge-<?php echo escapar($alerta['severidade']); ?>">
                                <?php echo escapar(AlertaModel::rotuloSeveridade($alerta['severidade'])); ?>
                            </span>
                            <strong><?php echo escapar(SensorModel::rotulo($alerta['sensor_tipo'])); ?></strong>
                            <span>— <?php echo escapar($alerta['mensagem']); ?></span>
                        </div>
                        <div class="grafico-total-leituras">
                            <?php echo escapar($alerta['sensor_nome']); ?>
                            | Valor: <?php echo $alerta['valor'] === null ? '—' : escapar(number_format((float) $alerta['valor'], 2, ',', '.')); ?>
                            | <?php echo escapar(date('d/m/Y H:i', strtotime($alerta['criado_em']))); ?>
                            | <?php echo $resolvido ? 'Resolvido' : 'Pendente'; ?>
                        </div>
                    </div>
                    <?php if ($ehAdmin && !$resolvido): ?>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
                            <input type="hidden" name="acao" value="resolver">
                            <input type="hidden" name="alerta_id" value="<?php echo (int) $alerta['id']; ?>">
                            <button type="submit" class="btn-secundario">Marcar resolvido</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="nav-rodape">
    <a href="graficos_sensores.php">← Gráficos de sensores</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
