<?php
session_start();
require_once '../../../includes/auth.php';
exigirLogin();

require_once '../../config/conexao.php';

$eh_admin = ehAdmin();
$mensagem = '';
$erros = [];

// ── Cadastro de novo sensor (somente admin) ───────────────────────────────────
if ($eh_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {

    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $localizacao = trim($_POST['localizacao'] ?? '');
        $status = $_POST['status'] ?? 'ativo';

        $tipos_validos = ['temperatura', 'ruido', 'qualidade_ar', 'umidade', 'pressao', 'uv'];

        if (strlen($nome) < 3)
            $erros[] = 'Nome do sensor deve ter ao menos 3 caracteres.';
        if (!in_array($tipo, $tipos_validos))
            $erros[] = 'Tipo de sensor inválido.';
        if (!in_array($status, ['ativo', 'inativo']))
            $erros[] = 'Status inválido.';

        if (empty($erros)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sensor (nome, tipo, localizacao, status, criado_em)
                    VALUES (:nome, :tipo, :loc, :status, NOW())
                ");
                $stmt->execute([
                    ':nome' => $nome,
                    ':tipo' => $tipo,
                    ':loc' => $localizacao,
                    ':status' => $status,
                ]);
                if (function_exists('registrarLog')) {
                    registrarLog($pdo, $_SESSION['usuario_id'], 'SENSOR_CADASTRO', "Sensor: $nome ($tipo)");
                }
                $mensagem = "Sensor <strong>" . htmlspecialchars($nome, ENT_QUOTES) . "</strong> cadastrado com sucesso.";
            } catch (PDOException $e) {
                $erros[] = 'Erro ao salvar sensor: ' . $e->getMessage();
            }
        }
    }
}

// ── Alteração de status (somente admin) ──────────────────────────────────────
if ($eh_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'toggle_status') {

    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $sensor_id = (int) ($_POST['sensor_id'] ?? 0);
        $novo_status = $_POST['novo_status'] ?? '';

        if ($sensor_id > 0 && in_array($novo_status, ['ativo', 'inativo'])) {
            try {
                $stmt = $pdo->prepare("UPDATE sensor SET status = :s WHERE id = :id");
                $stmt->execute([':s' => $novo_status, ':id' => $sensor_id]);
                if (function_exists('registrarLog')) {
                    registrarLog($pdo, $_SESSION['usuario_id'], 'SENSOR_STATUS', "Sensor ID $sensor_id → $novo_status");
                }
                $mensagem = "Status do sensor atualizado para <strong>$novo_status</strong>.";
            } catch (PDOException $e) {
                $erros[] = 'Erro ao alterar status: ' . $e->getMessage();
            }
        }
    }
}

// ── Lista sensores ────────────────────────────────────────────────────────────
$sensores = [];
try {
    $sensores = $pdo->query("
        SELECT id, nome, tipo, localizacao, status, criado_em
        FROM sensor
        ORDER BY status DESC, nome ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erros[] = 'Tabela de sensores não encontrada.';
}

$csrf_token = gerarCSRF();

$labels_tipo = [
    'temperatura' => 'Temperatura',
    'ruido' => 'Ruído',
    'qualidade_ar' => 'Qualidade do Ar',
    'umidade' => 'Umidade',
    'pressao' => 'Pressão',
    'uv' => 'UV',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gestão de Sensores</title>
</head>

<body>

    <h1>Gestão de Sensores</h1>

    <p>Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
        | Tipo: <?php echo $eh_admin ? 'Administrador' : 'Funcionário'; ?></p>

    <?php if ($mensagem): ?>
        <div style="color:green;"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <?php if (!empty($erros)): ?>
        <div style="color:red;">
            <ul><?php foreach ($erros as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ── Cadastro de novo sensor (somente admin) ──────────────────────── -->
    <?php if ($eh_admin): ?>
        <hr>
        <h2>Adicionar Novo Sensor</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="acao" value="cadastrar">

            <label>Nome do sensor: <input type="text" name="nome" maxlength="100" required
                    placeholder="Ex: Sensor Galpão A"></label><br><br>

            <label>Tipo:
                <select name="tipo" required>
                    <option value="">— selecione —</option>
                    <?php foreach ($labels_tipo as $v => $l): ?>
                        <option value="<?php echo $v; ?>"><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </label><br><br>

            <label>Localização: <input type="text" name="localizacao" maxlength="150"
                    placeholder="Ex: Setor B, linha 3"></label><br><br>

            <label>Status:
                <select name="status">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </label><br><br>

            <button type="submit">Cadastrar Sensor</button>
        </form>
    <?php endif; ?>

    <!-- ── Lista de sensores ────────────────────────────────────────────── -->
    <hr>
    <h2>Sensores Cadastrados</h2>

    <?php if (empty($sensores)): ?>
        <p><em>Nenhum sensor cadastrado.</em></p>
    <?php else: ?>
        <table border="1" cellpadding="5" cellspacing="0">
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
                        <td><?php echo $s['status'] === 'ativo' ? '✅ Ativo' : '❌ Inativo'; ?></td>
                        <td><?php echo htmlspecialchars($s['criado_em']); ?></td>
                        <?php if ($eh_admin): ?>
                            <td>
                                <?php $novo = $s['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="acao" value="toggle_status">
                                    <input type="hidden" name="sensor_id" value="<?php echo (int) $s['id']; ?>">
                                    <input type="hidden" name="novo_status" value="<?php echo $novo; ?>">
                                    <button type="submit">
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

    <br>
    <a href="/tcc/app/views/dashboard/painel.php">← Voltar ao painel</a>

</body>

</html>