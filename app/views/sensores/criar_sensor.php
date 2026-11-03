<?php
/**
 * app/views/sensores/criar_sensor.php — Cadastrar Sensor (somente administradores).
 *
 * Permite cadastrar um novo sensor (nome, tipo, localização e status).
 * A edição e a exclusão ficam em arquivos separados.
 * O formulário POST leva token CSRF e o SensorController valida tudo
 * novamente no servidor.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';

$controller = new SensorController($pdo, true);

// Força a ação apenas para cadastro — atualizar/excluir não são tratados aqui
$acao = $_POST['acao'] ?? '';
if ($acao !== '' && $acao !== 'cadastrar') {
    $acao = '';
    $_POST['acao'] = '';
}

$resultado = $controller->processarRequisicao();
['mensagem' => $mensagem, 'erros' => $erros] = $resultado;

// Valores do formulário: vazios por padrão; se o cadastro falhou, mantém o que foi digitado
$sensorNovo = [
    'nome'        => '',
    'tipo'        => '',
    'localizacao' => '',
    'status'      => 'ativo',
];

if ($acao === 'cadastrar' && !empty($erros)) {
    foreach (array_keys($sensorNovo) as $campo) {
        if (isset($_POST[$campo])) {
            $sensorNovo[$campo] = $_POST[$campo];
        }
    }
}

$sensores = $controller->listarSensores($erros);
$csrf_token = gerarCSRF();

$tituloPagina = 'Cadastrar Sensor';
$secaoTopbar = 'Sensores';
$cssPagina = ['sensores.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Cadastrar Sensor</h1>

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

<!-- ── Formulário de cadastro ────────────────────────────────────────────── -->
<div class="card">
    <h2>Novo sensor</h2>
    <form method="POST" class="sensores-form-novo">
        <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
        <input type="hidden" name="acao" value="cadastrar">

        <div>
            <label for="novo_nome">Nome do sensor</label>
            <input type="text" id="novo_nome" name="nome" maxlength="100" required
                   value="<?php echo escapar($sensorNovo['nome']); ?>">
        </div>

        <div>
            <label for="novo_tipo">Tipo</label>
            <select id="novo_tipo" name="tipo" required>
                <option value="" disabled <?php echo $sensorNovo['tipo'] === '' ? 'selected' : ''; ?>>Selecione...</option>
                <?php foreach (SensorModel::TIPOS as $chave => $meta): ?>
                    <option value="<?php echo escapar($chave); ?>"
                        <?php echo $sensorNovo['tipo'] === $chave ? 'selected' : ''; ?>>
                        <?php echo escapar($meta['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="novo_localizacao">Localização</label>
            <input type="text" id="novo_localizacao" name="localizacao" maxlength="150"
                   value="<?php echo escapar($sensorNovo['localizacao']); ?>">
        </div>

        <div>
            <label for="novo_status">Status</label>
            <select id="novo_status" name="status">
                <option value="ativo" <?php echo $sensorNovo['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                <option value="inativo" <?php echo $sensorNovo['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </div>

        <div class="campo-full">
            <button type="submit">Cadastrar sensor</button>
            <a href="gestao_sensores.php">Cancelar</a>
        </div>
    </form>
</div>

<!-- ── Sensores já cadastrados (referência) ──────────────────────────────── -->
<div class="card">
    <h2>Sensores cadastrados</h2>

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