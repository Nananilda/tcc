<?php
/**
 * app/views/usuarios/listar.php — Listagem de usuários (somente admin).
 *
 * Consulta somente leitura dos usuários cadastrados no sistema.
 * Nunca exibe a senha (nem o hash).
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';

$controller = new UsuarioController($pdo, true, (int) $_SESSION['usuario_id']);

$erros = [];
$usuarios = [];

try {
    $usuarios = $controller->listar();
} catch (Throwable $e) {
    error_log('Erro ao listar usuários: ' . $e->getMessage());
    $erros[] = 'Não foi possível carregar a lista de usuários.';
}

$tituloPagina = 'Listar Usuários';
$secaoTopbar = 'Usuários';
$cssPagina = ['usuarios.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Usuários Cadastrados</h1>

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
    <?php if (empty($usuarios)): ?>
        <p class="msg-vazio">Nenhum usuário cadastrado.</p>
    <?php else: ?>
        <p class="usuarios-dica">Total: <?php echo count($usuarios); ?> usuário(s).</p>
        <div class="tabela-rolagem">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Login</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Cadastrado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <?php $ativo = ($u['status'] ?? '') === 'ativo'; ?>
                        <tr>
                            <td><?php echo (int) $u['id']; ?></td>
                            <td><?php echo escapar($u['nome']); ?></td>
                            <td><?php echo escapar($u['login']); ?></td>
                            <td><?php echo escapar(rotuloTipoUsuario($u['tipo'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $ativo ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $ativo ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo !empty($u['criado_em'])
                                    ? escapar(date('d/m/Y H:i', strtotime($u['criado_em'])))
                                    : '—'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="nav-rodape">
    <a href="gestao_usuarios.php">← Voltar à gestão de usuários</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>