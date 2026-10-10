<?php
/**
 * app/views/graficos/novo_alerta.php — Novo alerta (somente admin).
 *
 * Reúne as duas formas de um alerta nascer no sistema:
 *   1) MANUAL: o administrador escolhe o sensor, a severidade e escreve a
 *      mensagem (manutenção programada, aviso da equipe, ocorrência observada...);
 *   2) AUTOMÁTICA: ao registrar uma leitura, o valor é comparado com os limites
 *      de cada tipo de sensor (AlertaModel::LIMITES) e, se passar, o alerta é
 *      criado sozinho. O formulário "Registrar leitura" simula o envio de um
 *      sensor — útil para testar e para demonstrar o recurso.
 * A tabela do final mostra os limites em vigor. Todos os formulários são POST
 * protegidos por token CSRF.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/AlertaController.php';
require_once __DIR__ . '/../../controllers/LeituraController.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$sensores = [];
$mensagem = '';
$erros = [];
$aba = ''; // formulário que recebeu o POST: 'criar' ou 'registrar_leitura'

try {
    $sensores = (new SensorModel($pdo))->listarTodos();
} catch (PDOException $e) {
    error_log('[' . APP_NOME . '] Erro ao listar sensores (novo alerta): ' . $e->getMessage());
    $erros[] = 'Não foi possível carregar a lista de sensores.';
}

// Valores do formulário de alerta manual (repreenchidos se houver erro)
$formAlerta = ['sensor_id' => '', 'severidade' => 'atencao', 'mensagem' => '', 'valor' => ''];
$formLeitura = ['sensor_id' => '', 'valor' => ''];
$alertaGerado = null; // alerta criado/encontrado pela leitura (para o link e o destaque)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aba = (string) ($_POST['acao'] ?? '');

    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros = ['Token de segurança inválido. Recarregue a página.'];
    } elseif ($aba === 'criar') {
        $resposta = (new AlertaController($pdo, true))->executar('criar', $_POST);
        $erros = $resposta['erros'];
        $mensagem = $resposta['mensagem'];
        if ($mensagem === '') {
            foreach (array_keys($formAlerta) as $campo) {
                $formAlerta[$campo] = trim((string) ($_POST[$campo] ?? $formAlerta[$campo]));
            }
        }
    } elseif ($aba === 'registrar_leitura') {
        $resposta = (new LeituraController($pdo))->registrar($_POST, $usuarioId);
        $erros = $resposta['erros'];
        $mensagem = $resposta['mensagem'];
        $alertaGerado = $resposta['alerta'] ?? null;
        if ($mensagem === '') {
            $formLeitura = ['sensor_id' => trim((string) ($_POST['sensor_id'] ?? '')), 'valor' => trim((string) ($_POST['valor'] ?? ''))];
        }
    } else {
        $erros = ['Ação desconhecida.'];
    }
}

/** Texto da faixa de um limite (ex.: "acima de 30 °C" ou "abaixo de 35% / acima de 80%"). */
function textoDoLimite(string $tipo, string $severidade): string
{
    $regras = AlertaModel::LIMITES[$tipo] ?? [];
    $unidade = SensorModel::unidade($tipo);
    $partes = [];
    if (isset($regras['min'][$severidade])) {
        $partes[] = 'abaixo de ' . AlertaModel::formatarLimite($regras['min'][$severidade], $unidade);
    }
    if (isset($regras['max'][$severidade])) {
        $partes[] = 'acima de ' . AlertaModel::formatarLimite($regras['max'][$severidade], $unidade);
    }
    return $partes ? implode(' ou ', $partes) : '—';
}

$csrf_token = gerarCSRF();

$tituloPagina = 'Novo Alerta';
$secaoTopbar = 'Alertas';
$cssPagina = ['graficos.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Novo Alerta</h1>
<p class="alertas-dica">
    Crie um alerta manualmente ou registre uma leitura para ver os alertas automáticos em ação.
</p>

<?php if ($mensagem): ?>
    <div class="msg-sucesso" role="status">
        <?php echo escapar($mensagem); ?>
        <?php if ($aba === 'criar' || $alertaGerado !== null): ?>
            <a href="alerta.php">Ver alertas →</a>
        <?php endif; ?>
    </div>
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

<!-- ── 1) Alerta manual ──────────────────────────────────────────────────── -->
<div class="card">
    <h2>Adicionar alerta manualmente</h2>
    <form method="POST" class="alertas-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
        <input type="hidden" name="acao" value="criar">

        <div>
            <label for="alerta_sensor">Sensor *</label>
            <select id="alerta_sensor" name="sensor_id" required>
                <option value="">— selecione —</option>
                <?php foreach ($sensores as $sensor): ?>
                    <option value="<?php echo (int) $sensor['id']; ?>"
                        <?php echo (string) $sensor['id'] === $formAlerta['sensor_id'] ? 'selected' : ''; ?>>
                        <?php echo escapar($sensor['nome'] . ' — ' . SensorModel::rotulo($sensor['tipo'])
                            . ($sensor['status'] === 'ativo' ? '' : ' (inativo)')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="alerta_severidade">Severidade *</label>
            <select id="alerta_severidade" name="severidade" required>
                <?php foreach (AlertaModel::SEVERIDADES as $chave => $rotulo): ?>
                    <option value="<?php echo escapar($chave); ?>"
                        <?php echo $chave === $formAlerta['severidade'] ? 'selected' : ''; ?>>
                        <?php echo escapar($rotulo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="alerta_valor">Valor medido (opcional)</label>
            <input type="text" id="alerta_valor" name="valor" inputmode="decimal" maxlength="14"
                placeholder="Ex: 36,5" value="<?php echo escapar($formAlerta['valor']); ?>">
        </div>

        <div class="campo-full">
            <label for="alerta_mensagem">Mensagem * <span class="alertas-dica">(3 a 200 caracteres)</span></label>
            <input type="text" id="alerta_mensagem" name="mensagem" maxlength="200" required
                placeholder="Ex: Manutenção programada da prensa 02 amanhã às 8h"
                value="<?php echo escapar($formAlerta['mensagem']); ?>">
        </div>

        <div class="campo-full">
            <button type="submit">Criar alerta</button>
        </div>
    </form>
</div>

<!-- ── 2) Alerta automático: registrar leitura ───────────────────────────── -->
<div class="card">
    <h2>Alertas automáticos — registrar leitura</h2>
    <p class="alertas-dica">
        Cada leitura enviada por um sensor é comparada com os limites abaixo. Se passar de algum,
        o alerta é criado sozinho (sem repetir um alerta igual que ainda esteja pendente). Este
        formulário simula o envio de uma leitura, para testar os limites.
    </p>
    <form method="POST" class="alertas-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
        <input type="hidden" name="acao" value="registrar_leitura">

        <div>
            <label for="leitura_sensor">Sensor ativo *</label>
            <select id="leitura_sensor" name="sensor_id" required>
                <option value="">— selecione —</option>
                <?php foreach ($sensores as $sensor): ?>
                    <?php if ($sensor['status'] !== 'ativo') { continue; } ?>
                    <option value="<?php echo (int) $sensor['id']; ?>"
                        <?php echo (string) $sensor['id'] === $formLeitura['sensor_id'] ? 'selected' : ''; ?>>
                        <?php echo escapar($sensor['nome'] . ' — ' . SensorModel::rotuloComUnidade($sensor['tipo'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="leitura_valor">Valor da leitura *</label>
            <input type="text" id="leitura_valor" name="valor" inputmode="decimal" maxlength="14" required
                placeholder="Ex: 36,5" value="<?php echo escapar($formLeitura['valor']); ?>">
        </div>

        <div class="alertas-form-botao">
            <button type="submit">Registrar leitura</button>
        </div>
    </form>
</div>

<!-- ── 3) Limites em vigor ───────────────────────────────────────────────── -->
<div class="card">
    <h2>Limites dos alertas automáticos</h2>
    <div class="tabela-rolagem">
        <table>
            <thead>
                <tr>
                    <th>Tipo de sensor</th>
                    <th>Atenção</th>
                    <th>Crítico</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (SensorModel::TIPOS as $tipo => $meta): ?>
                    <tr>
                        <td><?php echo escapar($meta['label']); ?></td>
                        <td><?php echo escapar(textoDoLimite($tipo, 'atencao')); ?></td>
                        <td><?php echo escapar(textoDoLimite($tipo, 'critico')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="alertas-dica">Para mudar um limite, edite <code>AlertaModel::LIMITES</code> em <code>app/models/AlertaModel.php</code>.</p>
</div>

<div class="nav-rodape">
    <a href="alerta.php">← Voltar aos alertas</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
