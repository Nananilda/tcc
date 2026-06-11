<?php
/**
 * includes/auth.php
 * Funções de autenticação, segurança e controle de acesso
 */

// ─── AUTENTICAÇÃO ─────────────────────────────────────────────────────────────

/**
 * Autentica usuário verificando login, senha hash, status e tipo
 */
function autenticarUsuario(PDO $pdo, string $login, string $senha): array
{
    // Sanitiza input
    $login = preg_replace('/[^a-zA-Z0-9._\-@]/', '', $login);

    if (empty($login) || empty($senha)) {
        return ['sucesso' => false, 'mensagem' => 'Credenciais inválidas.'];
    }

    $stmt = $pdo->prepare("
        SELECT id, nome, login, senha, tipo, status
        FROM usuario
        WHERE login = :login
        LIMIT 1
    ");
    $stmt->execute([':login' => $login]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Timing-safe: simula verificação mesmo quando usuário não existe
        password_verify('dummy', '$2y$12$dummyhashtopreventtiming');
        return ['sucesso' => false, 'mensagem' => 'Usuário ou senha incorretos.'];
    }

    if (!password_verify($senha, $usuario['senha'])) {
        return ['sucesso' => false, 'mensagem' => 'Usuário ou senha incorretos.'];
    }

    if ($usuario['status'] !== 'ativo') {
        return ['sucesso' => false, 'mensagem' => 'Conta desativada. Contate o administrador.'];
    }

    // Re-hash se o custo do algoritmo foi atualizado
    if (password_needs_rehash($usuario['senha'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $novoHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $pdo->prepare("UPDATE usuario SET senha = ? WHERE id = ?");
        $upd->execute([$novoHash, $usuario['id']]);
    }

    // Atualiza último acesso
    $atualiza = $pdo->prepare("UPDATE usuario SET ultimo_acesso = NOW() WHERE id = ?");
    $atualiza->execute([$usuario['id']]);

    unset($usuario['senha']); // Nunca mantém o hash na sessão
    return ['sucesso' => true, 'usuario' => $usuario];
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

function validarCSRF(string $token): bool
{
    if (!isset($_SESSION) || empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
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

// ─── BLOQUEIO POR IP ──────────────────────────────────────────────────────────

/**
 * Verifica se o IP está bloqueado por excesso de tentativas (5 em 15 min)
 */
function verificarBloqueioIP(PDO $pdo, string $ip): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as tentativas
        FROM logs_acesso
        WHERE ip = :ip
          AND acao = 'LOGIN_FALHA'
          AND criado_em >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([':ip' => $ip]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) $row['tentativas'] >= 5;
}

// ─── SESSÃO E PERMISSÕES ──────────────────────────────────────────────────────

/**
 * Garante que o usuário está autenticado; redireciona caso contrário
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
    if (isset($_SESSION['login_hora'])) {
        $inicio = strtotime($_SESSION['login_hora']);
        if ((time() - $inicio) > $timeout) {
            encerrarSessao();
            header('Location: /index.php?erro=timeout');
            exit;
        }
    }

    // Proteção contra session hijacking: valida IP
    if (isset($_SESSION['ip_login']) && $_SESSION['ip_login'] !== $_SERVER['REMOTE_ADDR']) {
        encerrarSessao();
        header('Location: /index.php?erro=seguranca');
        exit;
    }
}

/**
 * Exige que o usuário seja administrador
 */
function exigirAdmin(): void
{
    exigirLogin();
    if ($_SESSION['usuario_tipo'] !== 'admin') {
        http_response_code(403);
        // Verifica se o arquivo existe antes de incluí-lo
        if (file_exists('views/403.php')) {
            include 'views/403.php';
        } else {
            die('Acesso negado. Você não tem permissão para acessar esta área.');
        }
        exit;
    }
}

/**
 * Verifica se o usuário logado é admin
 */
function ehAdmin(): bool
{
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
}

/**
 * Encerra sessão com segurança
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

// ─── LOGS ─────────────────────────────────────────────────────────────────────

/**
 * Registra log de acesso ou ação no sistema
 *
 * @param PDO        $pdo
 * @param int|null   $usuario_id  null quando não autenticado
 * @param string     $acao        Ex: LOGIN_SUCESSO, LOGIN_FALHA, CADASTRO_USUARIO
 * @param string     $descricao   Detalhes livres
 */
function registrarLog(PDO $pdo, ?int $usuario_id, string $acao, string $descricao = ''): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_acesso (usuario_id, acao, descricao, ip, user_agent, criado_em)
            VALUES (:uid, :acao, :desc, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':uid' => $usuario_id,
            ':acao' => $acao,
            ':desc' => mb_substr($descricao, 0, 500),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
            ':ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (PDOException $e) {
        error_log('Log error: ' . $e->getMessage());
    }
}

// ─── VALIDAÇÃO DE DADOS ───────────────────────────────────────────────────────

function validarLogin(string $login): bool
{
    // Apenas letras, números, ponto, hífen e underscore. 3–50 chars.
    return (bool) preg_match('/^[a-zA-Z0-9._\-]{3,50}$/', $login);
}

function validarSenha(string $senha): array
{
    $erros = [];
    if (strlen($senha) < 8)
        $erros[] = 'Mínimo 8 caracteres.';
    if (!preg_match('/[A-Z]/', $senha))
        $erros[] = 'Pelo menos uma letra maiúscula.';
    if (!preg_match('/[a-z]/', $senha))
        $erros[] = 'Pelo menos uma letra minúscula.';
    if (!preg_match('/[0-9]/', $senha))
        $erros[] = 'Pelo menos um número.';
    if (!preg_match('/[\W_]/', $senha))
        $erros[] = 'Pelo menos um caractere especial.';
    return $erros;
}
?>