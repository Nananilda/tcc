<?php
/**
 * includes/auth.php
 * Funções de autenticação, segurança e controle de acesso do sistema ÁKRON.
 *
 * A autenticação valida login/senha contra a tabela `usuario` do banco real
 * (banco_tcc), usando password_verify() sobre o hash salvo por
 * UsuarioModel::criar(). Incluído por praticamente todas as páginas e pelos
 * endpoints da API.
 *
 * Organização do arquivo:
 *   1. Sessão                       iniciarSessao()
 *   2. Autenticação                 autenticarUsuario()
 *   3. Proteção CSRF                gerarCSRF(), validarCSRF()
 *   4. Bloqueio por IP e logs       verificarBloqueioIP(), registrarLog()
 *   5. Controle de acesso           verificarSessao(), exigirLogin(), exigirAdmin(), ehAdmin(), encerrarSessao()
 *   6. Validação de dados           validarLogin(), validarSenha()
 */

require_once __DIR__ . '/../app/config/app.php';

// ─── SESSÃO ─────────────────────────────────────────────────────────────────

/**
 * Inicia a sessão PHP (se ainda não estiver ativa) com cookie endurecido:
 *   - HttpOnly: o JavaScript da página não consegue ler o cookie de sessão;
 *   - SameSite=Lax: o navegador não envia o cookie em requisições
 *     cross-site disparadas por formulários externos (reduz CSRF).
 * É seguro chamar várias vezes.
 */
function iniciarSessao(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,          // cookie de sessão (some ao fechar o navegador)
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── AUTENTICAÇÃO ───────────────────────────────────────────────────────────

/**
 * Autentica um usuário contra a tabela `usuario`.
 *
 * @return array Em caso de sucesso: ['sucesso' => true, 'usuario' => array]
 *               (o hash da senha nunca é devolvido).
 *               Em caso de falha:   ['sucesso' => false, 'mensagem' => string].
 */
function autenticarUsuario(?PDO $pdo, string $login, string $senha): array
{
    // Remove caracteres que não fazem parte de um login válido
    $login = preg_replace('/[^a-zA-Z0-9._\- @]/', '', $login);

    if (empty($login) || empty($senha)) {
        return ['sucesso' => false, 'mensagem' => 'Credenciais inválidas.'];
    }

    if ($pdo === null) {
        return ['sucesso' => false, 'mensagem' => 'Banco de dados indisponível.'];
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM usuario WHERE login = :login LIMIT 1"
    );
    $stmt->execute([':login' => $login]);
    $usuario = $stmt->fetch();

    // Mesma mensagem para "usuário inexistente" e "senha errada": não revela
    // quais logins existem no sistema.
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
// Token aleatório guardado na sessão e reenviado em cada formulário POST;
// impede que outro site faça o navegador do usuário enviar formulários por ele.

/**
 * Confere se o token recebido do formulário é o mesmo da sessão.
 */
function validarCSRF(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Devolve o token CSRF da sessão (criando um novo, se necessário).
 */
function gerarCSRF(): string
{
    iniciarSessao();
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
 * Confere se existe uma sessão autenticada e válida, SEM redirecionar.
 * Aplica o timeout de sessão (2 horas) e a validação de IP.
 *
 * @return string|null null se a sessão é válida; caso contrário o motivo:
 *                     'sessao' (não logado), 'timeout' ou 'seguranca' (IP mudou).
 */
function verificarSessao(): ?string
{
    iniciarSessao();

    if (empty($_SESSION['usuario_id'])) {
        return 'sessao';
    }

    // Timeout de sessão: 2 horas
    $timeout = 7200;
    if (isset($_SESSION['login_hora']) && (time() - strtotime($_SESSION['login_hora'])) > $timeout) {
        encerrarSessao();
        return 'timeout';
    }

    // Proteção contra session hijacking: valida IP
    if (isset($_SESSION['ip_login']) && $_SESSION['ip_login'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        encerrarSessao();
        return 'seguranca';
    }

    return null;
}

/**
 * Garante que o usuário está autenticado; redireciona para o login caso contrário
 * (a tela de login explica o motivo pelo parâmetro ?erro=).
 */
function exigirLogin(): void
{
    $motivo = verificarSessao();
    if ($motivo !== null) {
        header('Location: ' . urlApp('index.php?erro=' . $motivo));
        exit;
    }
}

/**
 * Exige que o usuário seja administrador (mostra a página 403 caso contrário).
 */
function exigirAdmin(): void
{
    exigirLogin();
    if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
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
 * Encerra a sessão com segurança (limpa os dados, apaga o cookie e destrói a sessão).
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

/**
 * Login válido: apenas letras, números, ponto, hífen e underscore (3–50 caracteres).
 */
function validarLogin(string $login): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9._\-]{3,50}$/', $login);
}

/**
 * Valida a força da senha.
 *
 * @return string[] Lista de regras não atendidas (vazia = senha forte).
 */
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
