<?php
session_start();
require_once '../../../includes/auth.php';
exigirLogin();

require_once '../../config/conexao.php';
require_once '../../controllers/AlertaController.php';

$eh_admin   = ehAdmin();
$controller = new AlertaController($pdo, $eh_admin);

['mensagem' => $mensagem, 'erros' => $erros] = $controller->processarRequisicao();

$alertas   = $controller->listarAlertas();
$contagem  = $controller->contarPorSeveridade();
$csrf_token = gerarCSRF();

$labels_severidade = ['critico' => 'Crítico', 'atencao' => 'Atenção', 'info' => 'Informativo'];
$labels_sensor = [
    'temperatura'  => 'Temperatura',
    'ruido'        => 'Ruído',
    'qualidade_ar' => 'Qualidade do Ar',
    'umidade'      => 'Umidade',
    'pressao'      => 'Pressão',
    'uv'           => 'UV',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas — IndustrialOS</title>
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/graficos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../../public/assets/js/chart.js"></script>
</head>

<body>

    <div class="topbar">
        <div class="marca">IndustrialOS — Alertas</div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
            | Tipo: <?php echo $eh_admin ? 'Administrador' : 'Funcionário'; ?>
        </div>
    </div>

    <div class="container">

        <h1>Alertas dos Sensores</h1>

        <?php if ($mensagem): ?>
            <div class="msg-sucesso"><?php echo $mensagem; ?></div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <div class="msg-erro">
                <ul><?php foreach ($erros as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ── Cards de resumo por severidade ─────────────────────────────── -->
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

        <!-- ── Gráfico de barras (contagem por severidade) ────────────────── -->
        <div class="card grafico-wrapper">
            <canvas id="graficoAlertas" height="120"></canvas>
        </div>
        <script>
            criarGraficoBarras(
                'graficoAlertas',
                ['Crítico', 'Atenção', 'Informativo'],
                [<?php echo (int) $contagem['critico']; ?>, <?php echo (int) $contagem['atencao']; ?>, <?php echo (int) $contagem['info']; ?>],
                'Alertas ativos',
                'Alertas ativos por severidade',
                ['rgba(255, 93, 108, 0.7)', 'rgba(255, 176, 32, 0.7)', 'rgba(46, 168, 255, 0.7)']
            );
        </script>

        <!-- ── Lista detalhada de alertas ──────────────────────────────────── -->
        <div class="card">
            <h2>Histórico recente</h2>

            <?php if (empty($alertas)): ?>
                <p><em>Nenhum alerta registrado.</em></p>
            <?php else: ?>
                <ul class="lista-alertas">
                    <?php foreach ($alertas as $a): ?>
                        <li>
                            <div>
                                <span class="badge badge-<?php echo $a['resolvido'] ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $labels_severidade[$a['severidade']] ?? $a['severidade']; ?>
                                </span>
                                &nbsp;
                                <strong><?php echo htmlspecialchars($labels_sensor[$a['sensor_tipo']] ?? $a['sensor_tipo']); ?></strong>
                                — <?php echo htmlspecialchars($a['mensagem']); ?>
                                <div class="grafico-total-leituras">
                                    Valor: <?php echo htmlspecialchars((string) $a['valor']); ?>
                                    | <?php echo htmlspecialchars($a['criado_em']); ?>
                                    | <?php echo $a['resolvido'] ? 'Resolvido' : 'Pendente'; ?>
                                </div>
                            </div>
                            <?php if ($eh_admin && !$a['resolvido']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="acao" value="resolver">
                                    <input type="hidden" name="alerta_id" value="<?php echo (int) $a['id']; ?>">
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

    </div>

</body>

</html>
