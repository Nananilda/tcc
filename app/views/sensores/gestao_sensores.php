<?php
/**
 * app/views/sensores/gestao_sensores.php — Gestão de Sensores.
 *
 * Administradores cadastram novos sensores e ativam/desativam os existentes;
 * os demais usuários veem apenas a lista (o formulário e a coluna "Ação" só
 * aparecem para admin — e o SensorController também recusa a ação no servidor).
 * Todos os formulários POST levam um token CSRF.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';

$ehAdmin = ehAdmin();
$controller = new SensorController($pdo, $ehAdmin);

// Se for um POST (cadastrar ou ativar/desativar), executa antes de listar
['mensagem' => $mensagem, 'erros' => $erros] = $controller->processarRequisicao();

$sensores = $controller->listarSensores($erros);
$csrf_token = gerarCSRF();

$tituloPagina = 'Gestão de Sensores';
$secaoTopbar = 'Sensores';
$cssPagina = ['sensores.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Gestão de Sensores</h1>

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

<!-- ── Cadastro de novo sensor (somente admin) ───────────────────────────── -->
<?php if ($ehAdmin): ?>
    <div class="card">
        <h2>Adicionar Novo Sensor</h2>
        <form method="POST" class="sensores-form-novo">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
            <input type="hidden" name="acao" value="cadastrar">

            <div>
                <label for="nome">Nome do sensor</label>
                <input type="text" id="nome" name="nome" maxlength="100" required placeholder="Ex: Sensor Galpão A">
            </div>

            <div>
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="">— selecione —</option>
                    <?php foreach (SensorModel::TIPOS as $chave => $meta): ?>
                        <option value="<?php echo escapar($chave); ?>"><?php echo escapar($meta['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="localizacao">Localização</label>
                <input type="text" id="localizacao" name="localizacao" maxlength="150" placeholder="Ex: Setor B, linha 3">
            </div>

            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>

            <div class="campo-full">
                <button type="submit">Cadastrar Sensor</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- ── Lista de sensores ─────────────────────────────────────────────────── -->
<div class="card">
    <h2>Sensores Cadastrados</h2>

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
                        <?php if ($ehAdmin): ?>
                            <th>Ação</th>
                        <?php endif; ?>
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
                            <?php if ($ehAdmin): ?>
                                <td>
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
                                        <input type="hidden" name="acao" value="toggle_status">
                                        <input type="hidden" name="sensor_id" value="<?php echo (int) $sensor['id']; ?>">
                                        <input type="hidden" name="novo_status" value="<?php echo $ativo ? 'inativo' : 'ativo'; ?>">
                                        <button type="submit" class="btn-secundario">
                                            <?php echo $ativo ? 'Desativar' : 'Ativar'; ?>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="nav-rodape">
    <a href="listar_sensores.php">Consulta somente leitura →</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
