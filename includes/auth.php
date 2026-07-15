<?php
/**
 * includes/auth.php
 * Funções de autenticação, segurança e controle de acesso
 */

// ─── AUTENTICAÇÃO ─────────────────────────────────────────────────────────────
// ─── AUTENTICAÇÃO MOCKADA PARA TESTE DE CSS ────────────────────────────────────

/**
 * Versão de testes: Ignora o banco e aceita logins fixos para testar CSS
 */
function autenticarUsuario(?PDO $pdo, string $login, string $senha): array {
    // Sanitiza input
    $login = preg_replace('/[^a-zA-Z0-9._\- @]/', '', $login);

    if (empty($login) || empty($senha)) {
        return ['sucesso' => false, 'mensagem' => 'Credenciais inválidas.'];
    }

    // Se o login digitado for exatamente "admin", cria sessão de administrador
    if ($login === 'admin') {
        $usuario = [
            'id' => 1,
            'nome' => 'Admin de Testes',
            'login' => 'admin',
            'tipo' => 'admin', // Garante que a função exigirAdmin() aprove
            'status' => 'ativo'
        ];
        return ['sucesso' => true, 'usuario' => $usuario];
    } 
    
    // Qualquer outro login digitado vai entrar como usuário comum
    else {
        $usuario = [
            'id' => 2,
            'nome' => 'Usuário Comum',
            'login' => $login,
            'tipo' => 'comum', // Cai no painel padrão
            'status' => 'ativo'
        ];
        return ['sucesso' => true, 'usuario' => $usuario];
    }
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

function validarCSRF(string $token): bool {
    // Retorna true para evitar que o token de formulários trave o CSS
    return true; 
}

function gerarCSRF(): string {
    if (!isset($_SESSION)) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ─── BLOQUEIO POR IP MOCKADO ──────────────────────────────────────────────────

/**
 * Força o retorno falso para nunca bloquear seu IP durante os testes
 */
function verificarBloqueioIP(?PDO $pdo, string $ip): bool {
    return false;
}

// ─── LOGS MOCKADOS ────────────────────────────────────────────────────────────

/**
 * Ignora o salvamento no banco e joga o log no arquivo de erros do PHP
 */
function registrarLog(?PDO $pdo, ?int $usuario_id, string $acao, string $descricao = ''): void {
    error_log("Log Simulado - Ação: $acao | Descrição: $descricao");
}


// ─── SESSÃO E PERMISSÕES ──────────────────────────────────────────────────────

/**
 * Garante que o usuário está autenticado; redireciona caso contrário
 */
function exigirLogin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Se não tiver ID do usuário, manda direto para a raiz do localhost sem acumular pastas
    if (empty($_SESSION['usuario_id'])) {
        header('Location: http://localhost:8000/index.php?erro=sessao');
        exit;
    }

    // Timeout de sessão: 2 horas
    $timeout = 7200;
    if (isset($_SESSION['login_hora'])) {
        $inicio = strtotime($_SESSION['login_hora']);
        if ((time() - $inicio) > $timeout) {
            encerrarSessao();
            header('Location: http://localhost:8000/index.php?erro=timeout');
            exit;
        }
    }

    // Proteção contra session hijacking: valida IP
    if (isset($_SESSION['ip_login']) && $_SESSION['ip_login'] !== $_SERVER['REMOTE_ADDR']) {
        encerrarSessao();
        header('Location: http://localhost:8000/index.php?erro=seguranca');
        exit;
    }
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

/**
 * Exige que o usuário seja administrador
 */
function exigirAdmin(): void {
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
function ehAdmin(): bool {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
}

/**
 * Encerra sessão com segurança
 */
function encerrarSessao(): void {
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

// ─── VALIDAÇÃO DE DADOS ───────────────────────────────────────────────────────

function validarLogin(string $login): bool {
    // Apenas letras, números, ponto, hífen e underscore. 3–50 chars.
    return (bool) preg_match('/^[a-zA-Z0-9._\-]{3,50}$/', $login);
}

function validarSenha(string $senha): array {
    $erros = [];
    if (strlen($senha) < 8)                        $erros[] = 'Mínimo 8 caracteres.';
    if (!preg_match('/[A-Z]/', $senha))            $erros[] = 'Pelo menos uma letra maiúscula.';
    if (!preg_match('/[a-z]/', $senha))            $erros[] = 'Pelo menos uma letra minúscula.';
    if (!preg_match('/[0-9]/', $senha))            $erros[] = 'Pelo menos um número.';
    if (!preg_match('/[\W_]/', $senha))            $erros[] = 'Pelo menos um caractere especial.';
    return $erros;
}
?>
