<?php
/**
 * app/views/sensores/excluir_sensores.php — Excluir Sensores (somente administradores).
 *
 * Permite excluir um sensor existente. A exclusão exige digitar a palavra
 * "excluir". O formulário POST leva token CSRF e o SensorController valida
 * tudo novamente no servidor.
 * A edição e o cadastro ficam em arquivos separados.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';

$controller = new SensorController($pdo, true);

// Força a ação apenas para exclusão — cadastrar/atualizar não são tratados aqui
$acao = $_POST['acao'] ?? '';
if ($acao !== '' && $acao !== 'excluir') {
    $acao = '';
    $_POST['acao'] = '';
}

$resultado = $controller->processarRequisicao();
['mensagem' => $mensagem, 'erros' => $erros] = $resultado;

// Sensor selecionado para exclusão (?id=123 ou retorno de um POST com erro)
$sensorId = (int) ($_POST['sensor_id'] ?? $_GET['id'] ?? 0);

// Se a exclusão acabou de ser feita com sucesso, limpa a seleção
if ($acao === 'excluir' && empty($erros)) {
    $sensorId = 0;
}

$sensorExclusao = null;
if ($sensorId > 0) {
    $sensorExclusao = $controller->buscarSensor($sensorId);
    if ($sensorExclusao === null && $acao !== 'excluir') {
        $erros[] = 'Sensor não encontrado.';
    }
}

$sensores = $controller->listarSensores($erros);
$csrf_token = gerarCSRF();

$tituloPagina = 'Excluir Sensores';
$secaoTopbar = 'Sensores';
$cssPagina = ['sensores.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Excluir Sensores</h1>

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

<!-- ── Exclusão do sensor selecionado ────────────────────────────────────── -->
<?php if ($sensorExclusao): ?>
    <div class="card sensores-zona-perigo">
        <h2>Excluir sensor #<?php echo (int) $sensorExclusao['id']; ?></h2>
        <p><strong>Nome:</strong> <?php echo escapar($sensorExclusao['nome']); ?><br>
           <strong>Tipo:</strong> <?php echo escapar(SensorModel::rotulo($sensorExclusao['tipo'])); ?><br>
           <strong>Localização:</strong> <?php echo escapar($sensorExclusao['localizacao'] ?? '—'); ?></p>
        <p>Esta ação é <strong>permanente</strong> e não pode ser desfeita. Para confirmar, digite
            <strong>excluir</strong> no campo abaixo.</p>
        <form method="POST" class="sensores-form-excluir" id="form-excluir">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="sensor_id" value="<?php echo (int) $sensorExclusao['id']; ?>">

            <label for="confirmacao">Digite “excluir” para confirmar</label>
            <input type="text" id="confirmacao" name="confirmacao" autocomplete="off" required
                   placeholder="excluir">

            <button type="submit" class="btn-perigo" id="btn-excluir">Excluir sensor</button>
            <a href="excluir_sensores.php">Cancelar</a>
        </form>
    </div>

    <script>
        // Conveniência: só habilita o botão quando a palavra estiver correta.
        // A validação real é feita no servidor (SensorController::processarEdicao).
        (function () {
            var campo = document.getElementById('confirmacao');
            var botao = document.getElementById('btn-excluir');
            if (!campo || !botao) return;
            botao.disabled = true;
            campo.addEventListener('input', function () {
                botao.disabled = campo.value.trim().toLowerCase() !== 'excluir';
            });
        })();
    </script>
<?php endif; ?>

<!-- ── Lista para escolher qual sensor excluir ───────────────────────────── -->
<div class="card">
    <h2>Escolha um sensor para excluir</h2>

    <?php if (empty($sensores)): ?>
        <p class="msg-vazio">Nenhum sensor cadastrado.</p>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Localização</th>
                        <th>Status</th>
                        <th>Cadastrado em</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sensores as $sensor): ?>
                        <?php $ativo = $sensor['status'] === 'ativo'; ?>
                        <tr>
                            <td><?php echo (int) $sensor['id']; ?></td>
                            <td><?php echo escapar($sensor['nome']); ?></td>
                            <td><?php echo escapar(SensorModel::rotulo($sensor['tipo'])); ?></td>
                            <td><?php echo escapar($sensor['localizacao'] ?? '—'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $ativo ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $ativo ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td><?php echo escapar(date('d/m/Y H:i', strtotime($sensor['criado_em']))); ?></td>
                            <td>
                                <a class="btn-perigo" href="excluir_sensores.php?id=<?php echo (int) $sensor['id']; ?>">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="nav-rodape">
    <a href="listar_sensores.php">Consulta somente leitura →</a>
    <a href="gestao_sensores.php">← Voltar à gestão de sensores</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>