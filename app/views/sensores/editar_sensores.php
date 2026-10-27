<?php
/**
 * app/views/sensores/editar_sensores.php — Editar Sensores (somente administradores).
 *
 * Permite editar os dados de um sensor existente (nome, tipo, localização e status).
 * O cadastro e a exclusão ficam em arquivos separados.
 * Todos os formulários POST levam token CSRF e o SensorController valida tudo
 * novamente no servidor.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';

$controller = new SensorController($pdo, true);

// Força a ação apenas para atualização — cadastrar/excluir não são tratados aqui
$acao = $_POST['acao'] ?? '';
if ($acao !== '' && $acao !== 'atualizar') {
    $acao = '';
    $_POST['acao'] = '';
}

$resultado = $controller->processarRequisicao();
['mensagem' => $mensagem, 'erros' => $erros] = $resultado;

// Sensor selecionado para edição (?id=123 ou retorno de um POST com erro)
$sensorId = (int) ($_POST['sensor_id'] ?? $_GET['id'] ?? 0);

$sensorEdicao = null;
if ($sensorId > 0) {
    $sensorEdicao = $controller->buscarSensor($sensorId);
    if ($sensorEdicao === null) {
        $erros[] = 'Sensor não encontrado.';
    }
}

// Se a atualização falhou, mantém o que o usuário digitou
if ($sensorEdicao && $acao === 'atualizar' && !empty($erros)) {
    foreach (['nome', 'tipo', 'localizacao', 'status'] as $campo) {
        if (isset($_POST[$campo])) {
            $sensorEdicao[$campo] = $_POST[$campo];
        }
    }
}

$sensores = $controller->listarSensores($erros);
$csrf_token = gerarCSRF();

$tituloPagina = 'Editar Sensores';
$secaoTopbar = 'Sensores';
$cssPagina = ['sensores.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Editar Sensores</h1>

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

<!-- ── Edição do sensor selecionado ──────────────────────────────────────── -->
<?php if ($sensorEdicao): ?>
    <div class="card">
        <h2>Editar sensor #<?php echo (int) $sensorEdicao['id']; ?></h2>
        <form method="POST" class="sensores-form-novo">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
            <input type="hidden" name="acao" value="atualizar">
            <input type="hidden" name="sensor_id" value="<?php echo (int) $sensorEdicao['id']; ?>">

            <div>
                <label for="ed_nome">Nome do sensor</label>
                <input type="text" id="ed_nome" name="nome" maxlength="100" required
                       value="<?php echo escapar($sensorEdicao['nome']); ?>">
            </div>

            <div>
                <label for="ed_tipo">Tipo</label>
                <select id="ed_tipo" name="tipo" required>
                    <?php foreach (SensorModel::TIPOS as $chave => $meta): ?>
                        <option value="<?php echo escapar($chave); ?>"
                            <?php echo $sensorEdicao['tipo'] === $chave ? 'selected' : ''; ?>>
                            <?php echo escapar($meta['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="ed_localizacao">Localização</label>
                <input type="text" id="ed_localizacao" name="localizacao" maxlength="150"
                       value="<?php echo escapar($sensorEdicao['localizacao'] ?? ''); ?>">
            </div>

            <div>
                <label for="ed_status">Status</label>
                <select id="ed_status" name="status">
                    <option value="ativo" <?php echo $sensorEdicao['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo $sensorEdicao['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                </select>
            </div>

            <div class="campo-full">
                <button type="submit">Salvar alterações</button>
                <a href="editar_sensores.php">Cancelar</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- ── Lista para escolher qual sensor editar ────────────────────────────── -->
<div class="card">
    <h2>Escolha um sensor para editar</h2>

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
                                <a class="btn-secundario" href="editar_sensores.php?id=<?php echo (int) $sensor['id']; ?>">Editar</a>
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