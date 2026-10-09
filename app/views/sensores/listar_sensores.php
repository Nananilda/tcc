<?php
/**
 * app/views/sensores/listar_sensores.php — Consulta de Sensores.
 *
 * Versão somente-leitura da listagem de sensores (sem cadastro nem alteração
 * de status), reaproveitando o mesmo SensorController de gestao_sensores.php.
 * Pensada para consulta rápida por qualquer usuário logado.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/SensorController.php';

$ehAdmin = ehAdmin();
$controller = new SensorController($pdo, $ehAdmin);

$erros = [];
$sensores = $controller->listarSensores($erros);

$tituloPagina = 'Consulta de Sensores';
$secaoTopbar = 'Sensores';
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Consulta de Sensores</h1>
<?php if ($ehAdmin): ?>
    <p>Lista somente leitura. Para cadastrar ou alterar sensores, acesse a
        <a href="gestao_sensores.php">Gestão de Sensores</a>.
    </p>
<?php else: ?>
    <p>Lista somente leitura dos sensores cadastrados.</p>
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

<div class="card">
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
                        <tr>
                            <td><?php echo (int) $sensor['id']; ?></td>
                            <td><?php echo escapar($sensor['nome']); ?></td>
                            <td><?php echo escapar(SensorModel::rotulo($sensor['tipo'])); ?></td>
                            <td><?php echo escapar($sensor['localizacao'] ?? '—'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $sensor['status'] === 'ativo' ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $sensor['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?>
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
    <a href="../graficos/graficos_sensores.php">Ver gráficos →</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
