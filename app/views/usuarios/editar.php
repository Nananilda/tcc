<?php
/**
 * editar.php
 * Busca um usuário pelo login e permite atualizar seus dados (somente admin).
 */

session_start();
require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';

// Inicializar variáveis
$usuario_encontrado = null;
$busca_realizada = false;
$erros = [];
$sucesso = '';
$dados = [
    'id' => '',
    'nome' => '',
    'login' => '',
    'tipo' => 'usuario',
    'status' => 'ativo'
];

$csrf_token = gerarCSRF();

// Processar busca de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_usuario'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $login_busca = trim($_POST['login_busca'] ?? '');

        if (empty($login_busca)) {
            $erros[] = 'Digite um login para buscar.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM usuario WHERE login = :login");
                $stmt->execute([':login' => $login_busca]);
                $usuario_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);
                $busca_realizada = true;

                if ($usuario_encontrado) {
                    $dados = [
                        'id' => $usuario_encontrado['id'],
                        'nome' => $usuario_encontrado['nome'],
                        'login' => $usuario_encontrado['login'],
                        'tipo' => $usuario_encontrado['tipo'],
                        'status' => $usuario_encontrado['status']
                    ];
                } else {
                    $erros[] = 'Usuário não encontrado com este login.';
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao buscar usuário: ' . $e->getMessage();
            }
        }
    }
}

// Processar atualização de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_usuario'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nome = trim($_POST['nome'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $tipo = $_POST['tipo'] ?? 'usuario';
        $status = $_POST['status'] ?? 'ativo';
        $senha = trim($_POST['senha'] ?? '');
        $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');

        if (!$id) {
            $erros[] = 'ID do usuário inválido.';
        }

        if (empty($nome) || strlen($nome) < 3 || strlen($nome) > 100) {
            $erros[] = 'Nome deve ter entre 3 e 100 caracteres.';
        }

        if (!validarLogin($login)) {
            $erros[] = 'Login deve ter entre 3 e 50 caracteres e pode conter letras, números, ponto, underline e hífen.';
        }

        if (!in_array($tipo, ['admin', 'usuario'], true)) {
            $erros[] = 'Tipo de usuário inválido.';
        }
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $erros[] = 'Status inválido.';
        }

        if (empty($erros)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM usuario WHERE login = :login AND id != :id");
                $stmt->execute([':login' => $login, ':id' => $id]);
                if ($stmt->fetch()) {
                    $erros[] = 'Este login já está em uso por outro usuário.';
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao verificar login.';
            }
        }

        if (!empty($senha)) {
            $erros_senha = validarSenha($senha);
            if (!empty($erros_senha)) {
                $erros = array_merge($erros, $erros_senha);
            }
            if ($senha !== $confirmar_senha) {
                $erros[] = 'As senhas não conferem.';
            }
        }

        if (empty($erros)) {
            try {
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $pdo->prepare("
                        UPDATE usuario
                        SET nome = :nome,
                            login = :login,
                            tipo = :tipo,
                            status = :status,
                            senha = :senha
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':login' => $login,
                        ':tipo' => $tipo,
                        ':status' => $status,
                        ':senha' => $senha_hash,
                        ':id' => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE usuario
                        SET nome = :nome,
                            login = :login,
                            tipo = :tipo,
                            status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':login' => $login,
                        ':tipo' => $tipo,
                        ':status' => $status,
                        ':id' => $id
                    ]);
                }

                if (function_exists('registrarLog')) {
                    registrarLog($pdo, $_SESSION['usuario_id'], 'EDICAO_USUARIO', "Usuário ID $id atualizado: $login");
                }

                $sucesso = 'Usuário atualizado com sucesso!';

                $dados = [
                    'id' => $id,
                    'nome' => $nome,
                    'login' => $login,
                    'tipo' => $tipo,
                    'status' => $status
                ];
                $usuario_encontrado = $dados;

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];

            } catch (PDOException $e) {
                $erros[] = 'Erro ao atualizar usuário: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário — IndustrialOS</title>
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/usuarios.css">
</head>
<body>

    <div class="topbar">
        <div class="marca">IndustrialOS — Usuários</div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
    </div>

    <div class="container">

        <h1>Editar Usuário</h1>
        <p class="usuarios-dica">Busque um usuário pelo login e edite suas informações.</p>

        <?php if ($sucesso): ?>
            <div class="msg-sucesso">✔ <?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <div class="msg-erro">
                <strong>Corrija os seguintes erros:</strong>
                <ul>
                    <?php foreach ($erros as $e): ?>
                        <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="usuarios-grid">
                    <div>
                        <label>Login do usuário a buscar</label>
                        <input
                            type="text"
                            name="login_busca"
                            placeholder="Digite o login do usuário"
                            value="<?php echo isset($_POST['login_busca']) ? htmlspecialchars($_POST['login_busca'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                            required
                        >
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <button type="submit" name="buscar_usuario" value="1">Buscar</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($usuario_encontrado): ?>
            <div class="card">
                <h2>Editar dados</h2>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $dados['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="atualizar_usuario" value="1">

                    <div class="usuarios-secao">
                        <div class="usuarios-secao-titulo">Dados do usuário</div>
                        <div class="usuarios-grid">
                            <div>
                                <label>ID</label>
                                <input type="text" value="<?php echo htmlspecialchars((string) $dados['id'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                            <div>
                                <label>Nome Completo *</label>
                                <input
                                    type="text"
                                    name="nome"
                                    maxlength="100"
                                    value="<?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>
                            <div>
                                <label>Login *</label>
                                <input
                                    type="text"
                                    name="login"
                                    maxlength="50"
                                    value="<?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?>"
                                    pattern="[a-zA-Z0-9._\-]{3,50}"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <div class="usuarios-secao">
                        <div class="usuarios-secao-titulo">Nova senha (opcional)</div>
                        <div class="usuarios-grid">
                            <div>
                                <label>Nova Senha</label>
                                <input type="password" name="senha" maxlength="128" placeholder="Deixe em branco para manter a atual">
                                <div class="usuarios-dica">Mín. 8 caracteres, maiúscula, minúscula, número e especial.</div>
                            </div>
                            <div>
                                <label>Confirmar Nova Senha</label>
                                <input type="password" name="confirmar_senha" maxlength="128" placeholder="Repita a nova senha">
                            </div>
                        </div>
                    </div>

                    <div class="usuarios-secao">
                        <div class="usuarios-secao-titulo">Nível de acesso</div>
                        <div class="usuarios-grid">
                            <div>
                                <label>Tipo de Usuário *</label>
                                <select name="tipo" required>
                                    <option value="usuario" <?php echo $dados['tipo'] === 'usuario' ? 'selected' : ''; ?>>Funcionário</option>
                                    <option value="admin" <?php echo $dados['tipo'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                </select>
                            </div>
                            <div>
                                <label>Status da Conta *</label>
                                <select name="status" required>
                                    <option value="ativo" <?php echo $dados['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="inativo" <?php echo $dados['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                                <div class="usuarios-dica">Usuários inativos não podem acessar o sistema.</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit">Salvar Alterações</button>
                </form>
            </div>
        <?php elseif ($busca_realizada && !$usuario_encontrado): ?>
            <div class="msg-erro">⚠ Nenhum usuário encontrado com este login.</div>
        <?php endif; ?>

        <div class="nav-rodape">
            <a href="gestao_usuarios.php">← Voltar à gestão de usuários</a>
            <a href="../dashboard/painel.php">← Voltar ao painel</a>
        </div>

    </div>

</body>
</html>
