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

define('AKRON_ACESSO_BANCO_LEGADO', true);
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

// ── Bloqueio por tentativas excessivas (5 falhas em 15 min, por IP ou login) ──
// A contagem real é feita no AuthController; aqui só se avisa e se desativa o
// formulário quando o IP já está bloqueado, com o tempo que realmente falta.
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$tentativasBloqueadas = false;
try {
    $estadoIp = statusBloqueioLogin($pdo, $ip);
    if ($estadoIp['bloqueado']) {
        $tentativasBloqueadas = true;
        $erro = mensagemBloqueioLogin($estadoIp['restante']);
    }
} catch (Throwable $e) {
    error_log('[ÁKRON] Falha ao consultar bloqueio: ' . $e->getMessage());
    $tentativasBloqueadas = true;
    $erro = 'Não foi possível verificar o acesso agora. Tente novamente em instantes.';
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
$cssPagina = [];
$cssPaginaFinal = ['login.css']; // por último: as regras da tela de login prevalecem sobre as globais
require __DIR__ . '/app/views/partials/head.php';
?>

<body class="tela-login">

    <main class="login-palco">
        <header class="login-marca">
            <h1 class="login-nome-sistema"><span class="logo logo-completa" role="img" aria-label="<?php echo escapar(APP_NOME); ?>"></span></h1>
            <p class="login-marca-tagline"><?php echo escapar(APP_LEMA); ?></p>
        </header>

        <section class="login-box" aria-labelledby="login-titulo">
            <h2 id="login-titulo">Entrar no ÁKRON</h2>
            <p class="login-subtitulo">Acompanhe os seus estoques.</p>

            <?php if ($erro): ?>
                <div class="msg-erro" role="alert"><?php echo escapar($erro); ?></div>
            <?php elseif ($aviso): ?>
                <div class="msg-alerta" role="status"><?php echo escapar($aviso); ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">

                <div class="login-campo">
                    <label for="login">Usuário</label>
                    <input type="text" id="login" name="login" placeholder="usuario.nome" maxlength="50"
                        value="<?php echo isset($_POST['login']) ? escapar($_POST['login']) : ''; ?>"
                        autocomplete="username" autofocus required <?php echo $tentativasBloqueadas ? 'disabled' : ''; ?>>
                </div>

                <div class="login-campo">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" maxlength="128"
                        autocomplete="current-password" required <?php echo $tentativasBloqueadas ? 'disabled' : ''; ?>>
                </div>

                <button type="submit" id="btnLogin" <?php echo $tentativasBloqueadas ? 'disabled' : ''; ?>>
                    Entrar
                </button>
            </form>

            <p class="login-versao">Acesso restrito a usuários autorizados.</p>
        </section>
    </main>

    <aside class="login-sistema" aria-label="Sobre o sistema">
        <strong><?php echo escapar(APP_NOME); ?></strong>
        <span><?php echo escapar('Sistema de ' . APP_DESCRICAO); ?></span>
        <span>Versão <?php echo escapar(APP_VERSAO); ?></span>
    </aside>

    <button type="button" class="ajuda-sistema btn-secundario" data-dialog="sobre-akron" aria-label="Sobre o ÁKRON" title="Sobre o ÁKRON">?</button>

    <dialog id="sobre-akron" aria-labelledby="sobre-titulo">
        <h2 id="sobre-titulo">Conheça o ÁKRON</h2>
        <p>Monitore as condições ambientais dos seus estoques por meio de sensores conectados.</p>
        <p>Acompanhe temperatura, umidade, qualidade do ar, ruído, pressão e índice UV, consulte históricos e relatórios e receba alertas quando as medições ultrapassarem os limites configurados.</p>
        <p>Por segurança, após 5 tentativas de acesso malsucedidas em 15 minutos o acesso é bloqueado temporariamente e liberado automaticamente depois desse período.</p>
        <form method="dialog"><button>Fechar explicação</button></form>
    </dialog>
</body>

</html>
