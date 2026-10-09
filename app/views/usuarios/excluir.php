<?php
/**
 * app/views/usuarios/excluir.php — Excluir usuário (somente admin).
 *
 * Fluxo em três etapas, para evitar exclusões acidentais:
 *   1) buscar o usuário pelo login            (POST buscar_usuario)
 *   2) conferir os dados e pedir confirmação  (POST confirmar_exclusao)
 *   3) excluir de fato                        (POST excluir_usuario)
 * O administrador não pode excluir a própria conta.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';

$usuarioLogadoId = (int) $_SESSION['usuario_id'];
$controller = new UsuarioController($pdo, true, $usuarioLogadoId);

$usuarioEncontrado = null;   // usuário exibido nas etapas 2 e 3
$confirmarExclusao = false;  // true na etapa 3 (quadro vermelho de confirmação)
$erros = [];
$sucesso = '';

// ── Etapa 1: buscar usuário pelo login ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_usuario'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $loginBusca = trim($_POST['login_busca'] ?? '');

        if ($loginBusca === '') {
            $erros[] = 'Digite um login para buscar.';
        } else {
            $encontrado = $controller->buscarPorLogin($loginBusca);
            if (!$encontrado) {
                $erros[] = 'Usuário não encontrado com este login.';
            } elseif ((int) $encontrado['id'] === $usuarioLogadoId) {
                $erros[] = 'Você não pode excluir seu próprio usuário.';
            } else {
                $usuarioEncontrado = $encontrado;
            }
        }
    }
}

// ── Etapa 2: pedir confirmação ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        // Recarrega o usuário pelo id enviado no passo anterior
        $encontrado = $controller->buscarPorId((int) ($_POST['id'] ?? 0));
        if ($encontrado && (int) $encontrado['id'] !== $usuarioLogadoId) {
            $usuarioEncontrado = $encontrado;
            $confirmarExclusao = true;
        } else {
            $erros[] = 'Usuário não encontrado para exclusão.';
        }
    }
}

// ── Etapa 3: excluir ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_usuario'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $resposta = $controller->excluir((int) ($_POST['id'] ?? 0));
        $erros = $resposta['erros'];
        $sucesso = $resposta['mensagem'];

        if ($sucesso !== '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // renova o token
        }
    }
}

$csrf_token = gerarCSRF();

$tituloPagina = 'Excluir Usuário';
$secaoTopbar = 'Usuários';
$cssPagina = ['usuarios.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Excluir Usuário</h1>
<p class="usuarios-dica">Busque um usuário pelo login e exclua seu cadastro.</p>

<?php if ($sucesso): ?>
    <div class="msg-sucesso" role="status"><?php echo escapar($sucesso); ?></div>
<?php endif; ?>

<?php if (!empty($erros)): ?>
    <div class="msg-erro" role="alert">
        <strong>Atenção:</strong>
        <ul>
            <?php foreach ($erros as $erro): ?>
                <li><?php echo escapar($erro); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Etapa 1: busca -->
<div class="card">
    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
        <div class="usuarios-grid">
            <div>
                <label for="login_busca">Login do usuário a buscar</label>
                <input type="text" id="login_busca" name="login_busca" placeholder="Digite o login do usuário"
                    value="<?php echo isset($_POST['login_busca']) ? escapar($_POST['login_busca']) : ''; ?>" required>
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="submit" name="buscar_usuario" value="1">Buscar</button>
            </div>
        </div>
    </form>
</div>

<?php if ($usuarioEncontrado): ?>
    <!-- Etapas 2 e 3 mostram os mesmos dados; muda só o quadro e os botões -->
    <div class="card <?php echo $confirmarExclusao ? 'usuarios-perigo-box' : ''; ?>">
        <h2><?php echo $confirmarExclusao ? 'Confirmar exclusão' : 'Usuário encontrado'; ?></h2>
        <?php if ($confirmarExclusao): ?>
            <p>Você está prestes a excluir permanentemente o seguinte usuário:</p>
        <?php endif; ?>

        <div class="tabela-rolagem">
            <table>
                <tbody>
                    <tr>
                        <td><strong>ID</strong></td>
                        <td><?php echo (int) $usuarioEncontrado['id']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Nome</strong></td>
                        <td><?php echo escapar($usuarioEncontrado['nome']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Login</strong></td>
                        <td><?php echo escapar($usuarioEncontrado['login']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tipo</strong></td>
                        <td><?php echo escapar(rotuloTipoUsuario($usuarioEncontrado['tipo'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status</strong></td>
                        <td>
                            <span class="badge badge-<?php echo $usuarioEncontrado['status'] === 'ativo' ? 'ativo' : 'inativo'; ?>">
                                <?php echo $usuarioEncontrado['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form method="POST" class="usuarios-acoes">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $usuarioEncontrado['id']; ?>">
            <?php if ($confirmarExclusao): ?>
                <p style="width:100%;margin:0"><strong>Esta ação é irreversível!</strong></p>
                <input type="hidden" name="excluir_usuario" value="1">
                <button type="submit" class="btn-perigo">Sim, excluir usuário</button>
                <a href="excluir.php" class="btn btn-secundario">Não, cancelar</a>
            <?php else: ?>
                <input type="hidden" name="confirmar_exclusao" value="1">
                <button type="submit" class="btn-perigo">Continuar para exclusão</button>
                <a href="excluir.php" class="btn btn-secundario">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>

<div class="nav-rodape">
    <a href="gestao_usuarios.php">← Voltar à gestão de usuários</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
