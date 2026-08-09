<?php
/**
 * includes/auth.php
 * Funções de autenticação, segurança e controle de acesso.
 *
 * A autenticação passou a validar o login/senha contra a tabela `usuario`
 * do banco real (banco_tcc), usando password_verify() sobre o hash salvo
 * por Usuario::criar(). A versão anterior era mockada (aceitava qualquer
 * senha e não consultava o banco); isso foi removido.
 */

// ─── AUTENTICAÇÃO ───────────────────────────────────────────────────────────

/**
 * Autentica um usuário contra a tabela `usuario`.
 * Retorna ['sucesso' => bool, 'usuario' => array|null, 'mensagem' => string].
 */
function autenticarUsuario(?PDO $pdo, string $login, string $senha): array
{
    $login = preg_replace('/[^a-zA-Z0-9._\- @]/', '', $login);

    if (empty($login) || empty($senha)) {
        return ['sucesso' => false, 'mensagem' => 'Credenciais inválidas.'];
    }

    if ($pdo === null) {
        return ['sucesso' => false, 'mensagem' => 'Banco de dados indisponível.'];
    }

    $stmt = $pdo->prepare(
        "SELECT id, nome, login, senha, tipo, status FROM usuario WHERE login = :login LIMIT 1"
    );
    $stmt->execute([':login' => $login]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        return ['sucesso' => false, 'mensagem' => 'Login ou senha inválidos.'];
    }

    if ($usuario['status'] !== 'ativo') {
        return ['sucesso' => false, 'mensagem' => 'Conta inativa. Contate um administrador.'];
    }

    unset($usuario['senha']);
    return ['sucesso' => true, 'usuario' => $usuario];
}

// ─── CSRF ───────────────────────────────────────────────────────────────────

function validarCSRF(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function gerarCSRF(): string
{
    if (!isset($_SESSION)) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ─── BLOQUEIO POR IP ────────────────────────────────────────────────────────

/**
 * Verifica bloqueio por tentativas excessivas de login.
 * Sem uma tabela de tentativas dedicada no banco atual, esta verificação
 * fica desativada (sempre libera o acesso); o ponto de extensão está
 * pronto para quando a tabela `tentativas_login` existir.
 */
function verificarBloqueioIP(?PDO $pdo, string $ip): bool
{
    return false;
}

// ─── LOGS ───────────────────────────────────────────────────────────────────

/**
 * Registra log de acesso ou ação no sistema no log de erros do PHP.
 *
 * @param PDO|null $pdo
 * @param int|null $usuario_id  null quando não autenticado
 * @param string   $acao        Ex: LOGIN_SUCESSO, LOGIN_FALHA, CADASTRO_USUARIO
 * @param string   $descricao   Detalhes livres
 */
function registrarLog(?PDO $pdo, ?int $usuario_id, string $acao, string $descricao = ''): void
{
    error_log("Log - Usuário: " . ($usuario_id ?? 'anônimo') . " | Ação: $acao | Descrição: $descricao");
}

// ─── SESSÃO E PERMISSÕES ────────────────────────────────────────────────────

/**
 * Garante que o usuário está autenticado; redireciona caso contrário.
 */
function exigirLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['usuario_id'])) {
        header('Location: /index.php?erro=sessao');
        exit;
    }

    // Timeout de sessão: 2 horas
    $timeout = 7200;
    if (isset($_SESSION['login_hora']) && (time() - strtotime($_SESSION['login_hora'])) > $timeout) {
        encerrarSessao();
        header('Location: /index.php?erro=timeout');
        exit;
    }

    // Proteção contra session hijacking: valida IP
    if (isset($_SESSION['ip_login']) && $_SESSION['ip_login'] !== $_SERVER['REMOTE_ADDR']) {
        encerrarSessao();
        header('Location: /index.php?erro=seguranca');
        exit;
    }
}

/**
 * Exige que o usuário seja administrador.
 */
function exigirAdmin(): void
{
    exigirLogin();
    if ($_SESSION['usuario_tipo'] !== 'admin') {
        http_response_code(403);
        if (file_exists(__DIR__ . '/../app/views/403.php')) {
            include __DIR__ . '/../app/views/403.php';
        } else {
            die('Acesso negado. Você não tem permissão para acessar esta área.');
        }
        exit;
    }
}

/**
 * Verifica se o usuário logado é admin.
 */
function ehAdmin(): bool
{
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
}

/**
 * Encerra sessão com segurança.
 */
function encerrarSessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'] ?? false,
            $p['httponly'] ?? true
        );
    }
    session_destroy();
}

// ─── VALIDAÇÃO DE DADOS ─────────────────────────────────────────────────────

function validarLogin(string $login): bool
{
    // Apenas letras, números, ponto, hífen e underscore. 3–50 chars.
    return (bool) preg_match('/^[a-zA-Z0-9._\-]{3,50}$/', $login);
}

function validarSenha(string $senha): array
{
    $erros = [];
    if (strlen($senha) < 8)             $erros[] = 'Mínimo 8 caracteres.';
    if (!preg_match('/[A-Z]/', $senha)) $erros[] = 'Pelo menos uma letra maiúscula.';
    if (!preg_match('/[a-z]/', $senha)) $erros[] = 'Pelo menos uma letra minúscula.';
    if (!preg_match('/[0-9]/', $senha)) $erros[] = 'Pelo menos um número.';
    if (!preg_match('/[\W_]/', $senha)) $erros[] = 'Pelo menos um caractere especial.';
    return $erros;
}
