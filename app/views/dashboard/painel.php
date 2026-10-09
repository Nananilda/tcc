<?php
/**
 * app/views/dashboard/painel.php — Painel principal.
 *
 * Mostra os números de resumo (sensores ativos, alertas pendentes, última
 * atualização) e os atalhos para as demais seções. A "Gestão de Sensores" e a
 * "Gestão de Cadastro" só aparecem para administradores.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/DashboardController.php';
require_once __DIR__ . '/../partials/icones.php';

$resumo = (new DashboardController($pdo))->obterResumo();

// Atalhos do menu: [ícone, texto, destino]. Os dois últimos são exclusivos de admin.
$atalhos = [
    ['grafico', 'Gráficos de Sensores', 'app/views/graficos/graficos_sensores.php'],
    ['alerta', 'Alertas', 'app/views/graficos/alerta.php'],
    ['relatorio', 'Relatórios', 'app/views/relatorios/relatorio_qualidade.php'],
    ['lista', 'Consulta de Sensores', 'app/views/sensores/listar_sensores.php'],
];
if (ehAdmin()) {
    $atalhos[] = ['sensor', 'Gestão de Sensores', 'app/views/sensores/gestao_sensores.php'];
    $atalhos[] = ['usuarios', 'Gestão de Cadastro', 'app/views/usuarios/gestao_usuarios.php'];
}

$tituloPagina = 'Painel';
$cssPagina = ['painel.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<div class="painel-boas-vindas">
    <h1>Painel</h1>
    <p class="subtitulo">Verifique como anda a produção da empresa</p>
</div>

<!-- Números de resumo -->
<div class="painel-stats">
    <div class="stat-card">
        <div class="stat-valor">
            <?php echo (int) $resumo['sensores_ativos']; ?> / <?php echo (int) $resumo['sensores_total']; ?>
        </div>
        <div class="stat-label">Sensores ativos</div>
    </div>
    <div class="stat-card">
        <div class="stat-valor"><?php echo (int) $resumo['alertas_pendentes']; ?></div>
        <div class="stat-label">Alertas pendentes</div>
    </div>
    <div class="stat-card">
        <div class="stat-valor"><?php echo escapar($resumo['ultima_atualizacao']); ?></div>
        <div class="stat-label">Última atualização</div>
    </div>
</div>

<!-- Atalhos para as seções -->
<nav class="painel-menu" aria-label="Seções do sistema">
    <?php foreach ($atalhos as [$icone, $texto, $destino]): ?>
        <a class="menu-item" href="<?php echo urlApp($destino); ?>">
            <span class="icone"><?php echo iconeSvg($icone); ?></span>
            <?php echo escapar($texto); ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
