<?php
/**
 * listar_sensores.php
 * Versão somente-leitura da listagem de sensores (sem cadastro/alteração
 * de status), reaproveitando o mesmo SensorController usado em
 * gestao_sensores.php. Pensada para consulta rápida por qualquer usuário
 * logado, sem as ações restritas ao administrador.
 */

session_start();
require_once '../../../includes/auth.php';
exigirLogin();
require_once '../../config/conexao.php';
require_once '../../controllers/SensorController.php';

$eh_admin = ehAdmin();
$controller = new SensorController($pdo, $eh_admin);

$erros = [];
$sensores = $controller->listarSensores($erros);
$labels_tipo = SensorController::TIPOS_VALIDOS;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Sensores — Ákron</title>
    <link rel="icon" type="image/svg+xml" href="../../../public/assets/img/logo.svg">
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/sensores.css">
</head>

<body>

    <div class="topbar">
        <div class="marca">
            <img src="../../../public/assets/img/logo.svg" alt="Ákron" class="marca-logo">
            <div class="marca-texto">
                <span class="marca-nome">ÁKRON</span>
                <span class="marca-secao">Sensores</span>
            </div>
        </div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
            | Tipo: <?php echo $eh_admin ? 'Administrador' : 'Funcionário'; ?>
        </div>
    </div>

    <div class="container">

        <h1>Consulta de Sensores</h1>
        <p>Lista somente leitura. Para cadastrar ou alterar sensores, acesse a
            <a href="gestao_sensores.php">Gestão de Sensores</a>.
        </p>

        <?php if (!empty($erros)): ?>
            <div class="msg-erro">
                <ul><?php foreach ($erros as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="nav-rodape">
            <a href="../graficos/graficos_sensores.php">Ver gráficos →</a>
            <a href="../dashboard/painel.php">← Voltar ao painel</a>
        </div>

    </div>

</body>

</html>