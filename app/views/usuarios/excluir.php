<?php
/**
 * excluir.php
 * Busca um usuário pelo login e permite excluí-lo, com etapa de confirmação
 * (somente admin; não é possível excluir o próprio usuário logado).
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
$confirmar_exclusao = false;
$dados = [
    'id' => '',
    'nome' => '',
    'login' => '',
    'tipo' => '',
    'status' => ''
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
                    if ((int) $usuario_encontrado['id'] === (int) $_SESSION['usuario_id']) {
                        $erros[] = 'Você não pode excluir seu próprio usuário.';
                        $usuario_encontrado = null;
                    } else {
                        $dados = [
                            'id' => $usuario_encontrado['id'],
                            'nome' => $usuario_encontrado['nome'],
                            'login' => $usuario_encontrado['login'],
                            'tipo' => $usuario_encontrado['tipo'],
                            'status' => $usuario_encontrado['status']
                        ];
                    }
                } else {
                    $erros[] = 'Usuário não encontrado com este login.';
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao buscar usuário: ' . $e->getMessage();
            }
        }
    }
}

// Processar exclusão de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_usuario'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $erros[] = 'ID do usuário inválido.';
        } elseif ($id === (int) $_SESSION['usuario_id']) {
            $erros[] = 'Você não pode excluir seu próprio usuário.';
        }

        if (empty($erros)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM usuario WHERE id = :id");
                $stmt->execute([':id' => $id]);

                if ($stmt->rowCount() > 0) {
                    if (function_exists('registrarLog')) {
                        registrarLog($pdo, $_SESSION['usuario_id'], 'EXCLUSAO_USUARIO', "Usuário ID $id excluído");
                    }

                    $sucesso = 'Usuário excluído com sucesso!';
                    $usuario_encontrado = null;
                    $busca_realizada = false;
                    $confirmar_exclusao = false;

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_token'];
                } else {
                    $erros[] = 'Usuário não encontrado para exclusão.';
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao excluir usuário: ' . $e->getMessage();
            }
        }
    }
}

// Processar confirmação de exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $confirmar_exclusao = true;
        // Reidrata os dados a partir do id enviado no passo anterior, já que
        // o usuário não foi re-buscado no banco nesta requisição.
        $id_confirmacao = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id_confirmacao) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = :id");
                $stmt->execute([':id' => $id_confirmacao]);
                $usuario_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($usuario_encontrado) {
                    $dados = [
                        'id' => $usuario_encontrado['id'],
                        'nome' => $usuario_encontrado['nome'],
                        'login' => $usuario_encontrado['login'],
                        'tipo' => $usuario_encontrado['tipo'],
                        'status' => $usuario_encontrado['status']
                    ];
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao carregar dados do usuário: ' . $e->getMessage();
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
    <title>Excluir Usuário — IndustrialOS</title>
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

        <h1>Excluir Usuário</h1>
        <p class="usuarios-dica">Busque um usuário pelo login e exclua seu cadastro.</p>

        <?php if ($sucesso): ?>
            <div class="msg-sucesso">✔ <?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <div class="msg-erro">
                <strong>Atenção:</strong>
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

        <?php if ($usuario_encontrado && !$confirmar_exclusao): ?>
            <div class="card">
                <h2>Usuário encontrado</h2>
                <table>
                    <tbody>
                        <tr><td><strong>ID</strong></td><td><?php echo htmlspecialchars((string) $dados['id'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td><strong>Nome</strong></td><td><?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td><strong>Login</strong></td><td><?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td><strong>Tipo</strong></td><td><?php echo $dados['tipo'] === 'admin' ? 'Administrador' : 'Funcionário'; ?></td></tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>
                                <span class="badge badge-<?php echo $dados['status'] === 'ativo' ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $dados['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <br>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $dados['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="confirmar_exclusao" value="1">
                    <button type="submit" class="btn-perigo">Continuar para exclusão</button>
                    <a href="excluir.php" class="btn btn-secundario">Cancelar</a>
                </form>
            </div>
        <?php elseif ($usuario_encontrado && $confirmar_exclusao): ?>
            <div class="card usuarios-perigo-box">
                <h2>⚠ Confirmar exclusão</h2>
                <p>Você está prestes a excluir permanentemente o seguinte usuário:</p>
                <table>
                    <tbody>
                        <tr><td><strong>ID</strong></td><td><?php echo htmlspecialchars((string) $dados['id'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td><strong>Nome</strong></td><td><?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td><strong>Login</strong></td><td><?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                    </tbody>
                </table>
                <p><strong>Esta ação é irreversível!</strong></p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $dados['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="excluir_usuario" value="1">
                    <button type="submit" class="btn-perigo">Sim, excluir usuário</button>
                    <a href="excluir.php" class="btn btn-secundario">Não, cancelar</a>
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
