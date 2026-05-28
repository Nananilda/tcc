<?php
session_start();

// Redireciona se já estiver logado
if (isset($_SESSION['usuario_id'])) {
    header('Location: app/views/dashboard/painel.php');
    exit;
}

require_once 'app/config/conexao.php';
require_once 'includes/auth.php';

$erro = '';
$tentativas_bloqueadas = false;

// Verifica bloqueio por tentativas excessivas (5 tentativas em 15 min)
$ip = $_SERVER['REMOTE_ADDR'];
if (function_exists('verificarBloqueioIP') && verificarBloqueioIP($pdo, $ip)) {
    $tentativas_bloqueadas = true;
    $erro = 'Acesso temporariamente bloqueado. Tente novamente em 15 minutos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tentativas_bloqueadas) {
    $login    = trim($_POST['login'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $token_csrf = $_POST['csrf_token'] ?? '';

    // Valida token CSRF
    if (!function_exists('validarCSRF') || !validarCSRF($token_csrf)) {
        $erro = 'Requisição inválida.  tente novamente.';
    } elseif (empty($login) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
        if (function_exists('registrarLog')) {
            registrarLog($pdo, null, 'LOGIN_FALHA', "Campos vazios - IP: $ip");
        }
    } else {
        if (!function_exists('autenticarUsuario')) {
            $erro = 'Erro interno: função de autenticação não encontrada.';
        } else {
            $resultado = autenticarUsuario($pdo, $login, $senha);

            if ($resultado['sucesso']) {
                $usuario = $resultado['usuario'];

                // Regenera sessão para evitar session fixation
                session_regenerate_id(true);

                $_SESSION['usuario_id']   = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];
                $_SESSION['login_hora']   = date('Y-m-d H:i:s');
                $_SESSION['ip_login']     = $ip;
                $_SESSION['csrf_token']   = bin2hex(random_bytes(32));

                if (function_exists('registrarLog')) {
                    registrarLog($pdo, $usuario['id'], 'LOGIN_SUCESSO', "Login: {$usuario['login']} - IP: $ip");
                }

                header('Location: app/views/dashboard/painel.php');
                exit;
            } else {
                $erro = $resultado['mensagem'];
                if (function_exists('registrarLog')) {
                    registrarLog($pdo, null, 'LOGIN_FALHA', "Login tentado: $login - IP: $ip - Motivo: {$resultado['mensagem']}");
                }
            }
        }
    }
}

// Gera token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IndustrialOS — Sistema de Monitoramento</title>
</head>
<body>

        <div>Sistema de Monitoramento Industrial v2.0</div>
    

    <div>
        <div>autenticação</div>

        <?php if ($erro): ?>
            <div>
                <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

            <div>
                <label for="login">Identificação do Usuário</label>
                <div>
                    <input
                        type="text"
                        id="login"
                        name="login"
                        placeholder="usuario.nome"
                        maxlength="50"
                        <?php echo $tentativas_bloqueadas ? 'disabled' : ''; ?>
                        value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <div>
                <label for="senha">Credencial de Acesso</label>
                <div>
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="••••••••"
                        maxlength="128"
                        <?php echo $tentativas_bloqueadas ? 'disabled' : ''; ?>
                        autocomplete="current-password"
                        required
                    >
                </div>
            </div>

            <button
                type="submit"
                id="btnLogin"
                <?php echo $tentativas_bloqueadas ? 'disabled' : ''; ?>
            >
                AUTENTICAR ACESSO
            </button>
        </form>

       
</div>

</body>
</html>