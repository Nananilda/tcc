<?php
/**
 * index.php — Tela de login do sistema ÁKRON.
 *
 * Fluxo: exibe o formulário; no POST valida o token CSRF e delega a
 * autenticação ao AuthController (a mesma usada pela API), que cria a sessão
 * e registra o log. Usuário já logado vai direto para o painel.
 *
 * Parâmetros opcionais na URL (usados pelos redirecionamentos do sistema):
 *   ?erro=sessao|timeout|seguranca   motivo pelo qual o usuário voltou ao login
 *   ?saiu=1                          mostra "Você saiu do sistema"
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/app/views/partials/icones.php';

iniciarSessao();

// Já autenticado: não faz sentido ver o login
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . urlApp('app/views/dashboard/painel.php'));
    exit;
}

require_once __DIR__ . '/app/config/conexao.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

$erro = '';
$aviso = '';

// ── Avisos vindos de redirecionamentos ───────────────────────────────────────
$motivosRedirecionamento = [
    'sessao' => 'Faça login para continuar.',
    'timeout' => 'Sua sessão expirou. Entre novamente.',
    'seguranca' => 'Sua sessão foi encerrada por segurança. Entre novamente.',
];
if (isset($_GET['erro'], $motivosRedirecionamento[$_GET['erro']])) {
    $aviso = $motivosRedirecionamento[$_GET['erro']];
} elseif (isset($_GET['saiu'])) {
    $aviso = 'Você saiu do sistema.';
}

// ── Bloqueio por tentativas excessivas (5 tentativas em 15 min) ──────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$tentativasBloqueadas = verificarBloqueioIP($pdo, $ip);
if ($tentativasBloqueadas) {
    $erro = 'Acesso temporariamente bloqueado. Tente novamente em 15 minutos.';
}

// ── Envio do formulário ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tentativasBloqueadas) {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tokenCsrf = $_POST['csrf_token'] ?? '';

    if (!validarCSRF($tokenCsrf)) {
        // Token inválido: normalmente a página ficou aberta por muito tempo
        $erro = 'Requisição inválida. Tente novamente.';
    } elseif ($login === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
        registrarLog($pdo, null, 'LOGIN_FALHA', "Campos vazios - IP: $ip");
    } else {
        $resultado = (new AuthController($pdo))->login($login, $senha, $ip);

        if ($resultado['sucesso']) {
            header('Location: ' . urlApp('app/views/dashboard/painel.php'));
            exit;
        }
        $erro = $resultado['mensagem'];
    }
}

// Token CSRF do formulário (criado na sessão se ainda não existir)
$csrf_token = gerarCSRF();

// ── Cabeçalho do documento ───────────────────────────────────────────────────
$cssPagina = ['login.css'];
require __DIR__ . '/app/views/partials/head.php';
?>

<body class="tela-login">

    <div class="login-titulo-sistema"><?php echo escapar(APP_DESCRICAO . ' v' . APP_VERSAO); ?></div>
    <div class="login-tema"><?php echo botaoTema(); ?></div>

    <header class="login-marca">
        <span class="logo logo-completa" role="img" aria-label="<?php echo escapar(APP_NOME); ?>"></span>
        <div class="login-marca-tagline"><?php echo escapar(APP_LEMA); ?></div>
    </header>

    <main class="login-box">
        <h2>Autenticação</h2>

        <?php if ($erro): ?>
            <div class="msg-erro" role="alert"><?php echo escapar($erro); ?></div>
        <?php elseif ($aviso): ?>
            <div class="msg-alerta" role="status"><?php echo escapar($aviso); ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">

            <div class="login-campo">
                <label for="login">Identificação do Usuário</label>
                <input type="text" id="login" name="login" placeholder="usuario.nome" maxlength="50"
                    value="<?php echo isset($_POST['login']) ? escapar($_POST['login']) : ''; ?>"
                    autocomplete="username" autofocus required <?php echo $tentativasBloqueadas ? 'disabled' : ''; ?>>
            </div>

            <div class="login-campo">
                <label for="senha">Credencial de Acesso</label>
                <input type="password" id="senha" name="senha" placeholder="••••••••" maxlength="128"
                    autocomplete="current-password" required <?php echo $tentativasBloqueadas ? 'disabled' : ''; ?>>
            </div>

            <button type="submit" id="btnLogin" <?php echo $tentativasBloqueadas ? 'disabled' : ''; ?>>
                AUTENTICAR ACESSO
            </button>
        </form>

        <p class="login-versao">Acesso restrito a usuários autorizados.</p>
    </main>

</body>

</html>
