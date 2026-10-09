<?php
/**
 * app/views/relatorios/relatorio_qualidade.php — Relatório de Qualidade Ambiental.
 *
 * Filtra as leituras dos sensores por tipo, período e faixa de valores e exibe
 * o resumo estatístico (média/mínimo/máximo por sensor) e as leituras
 * detalhadas. Os filtros chegam por GET e são validados no RelatorioModel.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/RelatorioController.php';

$controller = new RelatorioController($pdo);
$relatorio = $controller->consultar($_GET);

$filtros = $relatorio['filtros'];     // valores sanitizados (repreenchem o formulário)
$leituras = $relatorio['leituras'];   // até RelatorioModel::LIMITE_LEITURAS linhas
$resumo = $relatorio['resumo'];       // média/mín./máx. por tipo de sensor
$rotulosSensores = $controller->rotulosSensores();

/** Formata números no padrão brasileiro (1.234,56). */
function formatarNumero($valor): string
{
    return number_format((float) $valor, 2, ',', '.');
}

$tituloPagina = 'Relatório de Qualidade';
$secaoTopbar = 'Relatórios';
$cssPagina = ['relatorios.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Relatório de Qualidade Ambiental</h1>

<?php if ($relatorio['erro'] !== ''): ?>
    <div class="msg-erro" role="alert"><?php echo escapar($relatorio['erro']); ?></div>
<?php endif; ?>

<!-- ── Filtros ───────────────────────────────────────────────────────────── -->
<form method="GET">
    <fieldset>
        <legend>Filtros</legend>

        <div class="relatorio-filtros">
            <div>
                <label for="sensor">Sensor</label>
                <select id="sensor" name="sensor">
                    <option value="">— Todos —</option>
                    <?php foreach ($rotulosSensores as $tipo => $rotulo): ?>
                        <option value="<?php echo escapar($tipo); ?>" <?php echo $filtros['sensor'] === $tipo ? 'selected' : ''; ?>>
                            <?php echo escapar($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="data_ini">De</label>
                <input type="date" id="data_ini" name="data_ini" value="<?php echo escapar($filtros['data_ini']); ?>">
            </div>

            <div>
                <label for="data_fim">Até</label>
                <input type="date" id="data_fim" name="data_fim" value="<?php echo escapar($filtros['data_fim']); ?>">
            </div>

            <div>
                <label for="val_min">Valor mín.</label>
                <input type="number" id="val_min" name="val_min" step="any" value="<?php echo escapar($filtros['val_min']); ?>">
            </div>

            <div>
                <label for="val_max">Valor máx.</label>
                <input type="number" id="val_max" name="val_max" step="any" value="<?php echo escapar($filtros['val_max']); ?>">
            </div>
        </div>

        <div class="relatorio-acoes">
            <button type="submit">Filtrar</button>
            <a href="relatorio_qualidade.php">Limpar filtros</a>
        </div>
    </fieldset>
</form>

<hr>

<!-- ── Resumo estatístico ────────────────────────────────────────────────── -->
<?php if (!empty($resumo)): ?>
    <h2>Resumo do período</h2>
    <div class="tabela-rolagem">
        <table class="relatorio-resumo-tabela">
            <thead>
                <tr>
                    <th>Sensor</th>
                    <th>Leituras</th>
                    <th>Média</th>
                    <th>Mínimo</th>
                    <th>Máximo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumo as $linha): ?>
                    <tr>
                        <td><?php echo escapar($rotulosSensores[$linha['sensor_tipo']] ?? $linha['sensor_tipo']); ?></td>
                        <td><?php echo (int) $linha['total']; ?></td>
                        <td><?php echo formatarNumero($linha['media']); ?></td>
                        <td><?php echo formatarNumero($linha['minimo']); ?></td>
                        <td><?php echo formatarNumero($linha['maximo']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ── Leituras detalhadas ───────────────────────────────────────────────── -->
<h2>Leituras detalhadas
    <span class="relatorio-contagem">
        <?php echo count($leituras) >= RelatorioModel::LIMITE_LEITURAS
            ? '(últimas ' . RelatorioModel::LIMITE_LEITURAS . ')'
            : '(' . count($leituras) . ' registros)'; ?>
    </span>
</h2>

<?php if (empty($leituras)): ?>
    <p class="msg-vazio">Nenhuma leitura encontrada com os filtros aplicados.</p>
<?php else: ?>
    <div class="relatorio-tabela-detalhe">
        <table>
            <thead>
                <tr>
                    <th>Sensor</th>
                    <th>Valor</th>
                    <th>Data/Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leituras as $leitura): ?>
                    <tr>
                        <td>
                            <?php echo escapar($rotulosSensores[$leitura['sensor_tipo']] ?? $leitura['sensor_tipo']); ?>
                            <div class="usuarios-dica"><?php echo escapar($leitura['sensor_nome']); ?></div>
                        </td>
                        <td><?php echo formatarNumero($leitura['valor']); ?></td>
                        <td><?php echo escapar(date('d/m/Y H:i', strtotime($leitura['lido_em']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="nav-rodape">
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
