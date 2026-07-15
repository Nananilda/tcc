<?php
session_start();
require_once '../../../includes/auth.php';
exigirLogin();
require_once '../../config/conexao.php';
require_once '../../controllers/SensorController.php';

$eh_admin   = ehAdmin();
$controller = new SensorController($pdo, $eh_admin);

['mensagem' => $mensagem, 'erros' => $erros] = $controller->processarRequisicao();

$sensores    = $controller->listarSensores($erros);
$csrf_token  = gerarCSRF();
$labels_tipo = SensorController::TIPOS_VALIDOS;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gestão de Sensores</title>
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/sensores.css">
</head>

<body>

    <div class="topbar">
        <div class="marca">IndustrialOS — Sensores</div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
            | Tipo: <?php echo $eh_admin ? 'Administrador' : 'Funcionário'; ?>
        </div>
    </div>

    <div class="container">

    <h1>Gestão de Sensores</h1>

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

    <!-- ── Cadastro de novo sensor (somente admin) ──────────────────────── -->
    <?php if ($eh_admin): ?>
        <div class="card">
        <h2>Adicionar Novo Sensor</h2>
        <form method="POST" class="sensores-form-novo">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="acao" value="cadastrar">

            <div>
                <label>Nome do sensor</label>
                <input type="text" name="nome" maxlength="100" required
                    placeholder="Ex: Sensor Galpão A">
            </div>

            <div>
                <label>Tipo</label>
                <select name="tipo" required>
                    <option value="">— selecione —</option>
                    <?php foreach ($labels_tipo as $v => $l): ?>
                        <option value="<?php echo $v; ?>"><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Localização</label>
                <input type="text" name="localizacao" maxlength="150"
                    placeholder="Ex: Setor B, linha 3">
            </div>

            <div>
                <label>Status</label>
                <select name="status">
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

    <!-- ── Lista de sensores ────────────────────────────────────────────── -->
    <div class="card">
    <h2>Sensores Cadastrados</h2>

    <?php if (empty($sensores)): ?>
        <p><em>Nenhum sensor cadastrado.</em></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Localização</th>
                    <th>Status</th>
                    <th>Cadastrado em</th>
                    <?php if ($eh_admin): ?>
                        <th>Ação</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sensores as $s): ?>
                    <tr>
                        <td><?php echo (int) $s['id']; ?></td>
                        <td><?php echo htmlspecialchars($s['nome']); ?></td>
                        <td><?php echo htmlspecialchars($labels_tipo[$s['tipo']] ?? $s['tipo']); ?></td>
                        <td><?php echo htmlspecialchars($s['localizacao'] ?? '—'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $s['status'] === 'ativo' ? 'ativo' : 'inativo'; ?>">
                                <?php echo $s['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($s['criado_em']); ?></td>
                        <?php if ($eh_admin): ?>
                            <td>
                                <?php $novo = $s['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="acao" value="toggle_status">
                                    <input type="hidden" name="sensor_id" value="<?php echo (int) $s['id']; ?>">
                                    <input type="hidden" name="novo_status" value="<?php echo $novo; ?>">
                                    <button type="submit" class="btn-secundario">
                                        <?php echo $s['status'] === 'ativo' ? 'Desativar' : 'Ativar'; ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>

    <div class="nav-rodape">
        <a href="listar_sensores.php">Consulta somente leitura →</a>
        <a href="../dashboard/painel.php">← Voltar ao painel</a>
    </div>

    </div>

</body>

</html>
