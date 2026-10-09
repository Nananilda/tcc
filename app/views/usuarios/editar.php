<?php
/**
 * app/views/usuarios/editar.php — Editar usuário (somente admin).
 *
 * Dois passos na mesma página:
 *   1) buscar o usuário pelo login (formulário "buscar_usuario");
 *   2) alterar nome, login, perfil, status e, opcionalmente, a senha
 *      (formulário "atualizar_usuario").
 * As regras de validação ficam no UsuarioModel (compartilhadas com a API).
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';

$controller = new UsuarioController($pdo, true, (int) $_SESSION['usuario_id']);

$usuarioEncontrado = null; // dados exibidos no formulário de edição (null = não mostrar)
$erros = [];
$sucesso = '';

// ── Passo 1: buscar usuário pelo login ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_usuario'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $loginBusca = trim($_POST['login_busca'] ?? '');

        if ($loginBusca === '') {
            $erros[] = 'Digite um login para buscar.';
        } else {
            $usuarioEncontrado = $controller->buscarPorLogin($loginBusca) ?: null;
            if ($usuarioEncontrado === null) {
                $erros[] = 'Usuário não encontrado com este login.';
            }
        }
    }
}

// ── Passo 2: gravar as alterações ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_usuario'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $resposta = $controller->atualizar($_POST);
        $erros = $resposta['erros'];
        $sucesso = $resposta['mensagem'];

        if ($sucesso !== '') {
            // Recarrega do banco o que foi gravado e renova o token CSRF
            $usuarioEncontrado = $controller->buscarPorLogin(trim($_POST['login'] ?? '')) ?: null;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Se o admin editou a PRÓPRIA conta, mantém a topbar em dia
            if ($usuarioEncontrado && (int) $usuarioEncontrado['id'] === (int) $_SESSION['usuario_id']) {
                $_SESSION['usuario_nome'] = $usuarioEncontrado['nome'];
                $_SESSION['usuario_tipo'] = $usuarioEncontrado['tipo'];
            }
        } else {
            // Houve erro: mantém o formulário aberto com o que foi digitado
            $usuarioEncontrado = [
                'id' => (int) ($_POST['id'] ?? 0),
                'nome' => trim($_POST['nome'] ?? ''),
                'login' => trim($_POST['login'] ?? ''),
                'tipo' => $_POST['tipo'] ?? 'usuario',
                'status' => $_POST['status'] ?? 'ativo',
            ];
        }
    }
}

$csrf_token = gerarCSRF();

$tituloPagina = 'Editar Usuário';
$secaoTopbar = 'Usuários';
$cssPagina = ['usuarios.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Editar Usuário</h1>
<p class="usuarios-dica">Busque um usuário pelo login e edite suas informações.</p>

<?php if ($sucesso): ?>
    <div class="msg-sucesso" role="status"><?php echo escapar($sucesso); ?></div>
<?php endif; ?>

<?php if (!empty($erros)): ?>
    <div class="msg-erro" role="alert">
        <strong>Corrija os seguintes erros:</strong>
        <ul>
            <?php foreach ($erros as $erro): ?>
                <li><?php echo escapar($erro); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Passo 1: busca -->
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

<!-- Passo 2: edição (aparece depois de encontrar o usuário) -->
<?php if ($usuarioEncontrado): ?>
    <div class="card">
        <h2>Editar dados</h2>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $usuarioEncontrado['id']; ?>">
            <input type="hidden" name="atualizar_usuario" value="1">

            <div class="usuarios-secao">
                <div class="usuarios-secao-titulo">Dados do usuário</div>
                <div class="usuarios-grid">
                    <div>
                        <label for="id_exibido">ID</label>
                        <input type="text" id="id_exibido" value="<?php echo (int) $usuarioEncontrado['id']; ?>" readonly>
                    </div>
                    <div>
                        <label for="nome">Nome Completo *</label>
                        <input type="text" id="nome" name="nome" maxlength="100"
                            value="<?php echo escapar($usuarioEncontrado['nome']); ?>" required>
                    </div>
                    <div>
                        <label for="login">Login *</label>
                        <input type="text" id="login" name="login" maxlength="50"
                            value="<?php echo escapar($usuarioEncontrado['login']); ?>"
                            pattern="[a-zA-Z0-9._\-]{3,50}" required>
                    </div>
                </div>
            </div>

            <div class="usuarios-secao">
                <div class="usuarios-secao-titulo">Nova senha (opcional)</div>
                <div class="usuarios-grid">
                    <div>
                        <label for="senha">Nova Senha</label>
                        <input type="password" id="senha" name="senha" maxlength="128"
                            placeholder="Deixe em branco para manter a atual">
                        <div class="usuarios-dica">Mín. 8 caracteres, maiúscula, minúscula, número e especial.</div>
                    </div>
                    <div>
                        <label for="confirmar_senha">Confirmar Nova Senha</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" maxlength="128"
                            placeholder="Repita a nova senha">
                    </div>
                </div>
            </div>

            <div class="usuarios-secao">
                <div class="usuarios-secao-titulo">Nível de acesso</div>
                <div class="usuarios-grid">
                    <div>
                        <label for="tipo">Tipo de Usuário *</label>
                        <select id="tipo" name="tipo" required>
                            <option value="usuario" <?php echo $usuarioEncontrado['tipo'] === 'usuario' ? 'selected' : ''; ?>>
                                <?php echo escapar(rotuloTipoUsuario('usuario')); ?></option>
                            <option value="admin" <?php echo $usuarioEncontrado['tipo'] === 'admin' ? 'selected' : ''; ?>>
                                <?php echo escapar(rotuloTipoUsuario('admin')); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="status">Status da Conta *</label>
                        <select id="status" name="status" required>
                            <option value="ativo" <?php echo $usuarioEncontrado['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo $usuarioEncontrado['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                        <div class="usuarios-dica">Usuários inativos não podem acessar o sistema.</div>
                    </div>
                </div>
            </div>

            <button type="submit">Salvar Alterações</button>
        </form>
    </div>
<?php endif; ?>

<div class="nav-rodape">
    <a href="gestao_usuarios.php">← Voltar à gestão de usuários</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
