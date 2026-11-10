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
 *   4. Bloqueio de tentativas e logs verificarBloqueioIP(), reservarTentativaLogin(), registrarLog()
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

    configurarArmazenamentoSessao();

    session_set_cookie_params([
        'lifetime' => 0,          // cookie de sessão (some ao fechar o navegador)
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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
function validarCSRF($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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

// ─── BLOQUEIO POR TENTATIVAS DE LOGIN ───────────────────────────────────────
// Cada tentativa é gravada em `tentativas_login` ANTES de a senha ser conferida
// (assim requisições simultâneas também são contadas). Falhas nunca são
// "perdoadas" por um login bem-sucedido, para que alguém com uma conta própria
// não consiga zerar o contador. Só o IP real da conexão (REMOTE_ADDR) é usado:
// cabeçalhos como X-Forwarded-For podem ser forjados e anulariam a proteção.
// Regra: LOGIN_MAX_TENTATIVAS falhas em LOGIN_JANELA_SEGUNDOS, contadas por IP
// e também por login (este último barra ataques distribuídos em vários IPs).
// O bloqueio termina sozinho quando as falhas mais antigas saem da janela.

const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_JANELA_SEGUNDOS = 900; // 15 minutos

/** Padroniza o login para contagem (minúsculas, sem caracteres inválidos, até 100). */
function loginParaContagem(string $login): string
{
    $login = preg_replace('/[^a-zA-Z0-9._\-@ ]/', '', trim($login)) ?? '';
    return substr(function_exists('mb_strtolower') ? mb_strtolower($login) : strtolower($login), 0, 100);
}

/** Cria a tabela se a instalação ainda não passou pela migração (idempotente). */
function garantirTabelaTentativas(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS tentativas_login (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        login VARCHAR(100) NOT NULL DEFAULT '',
        sucesso TINYINT(1) NOT NULL DEFAULT 0,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tentativa_ip (ip, sucesso, criado_em),
        INDEX idx_tentativa_login (login, sucesso, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Executa $acao tolerando a ausência da tabela (cria e repete uma vez). */
function comTabelaTentativas(PDO $pdo, callable $acao)
{
    try {
        return $acao();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) !== 1146) throw $e; // 1146 = tabela inexistente
        garantirTabelaTentativas($pdo);
        return $acao();
    }
}

/**
 * Situação do bloqueio para um IP (e, se informado, um login).
 *
 * @return array{bloqueado:bool, restante:int} `restante` em segundos até a liberação.
 */
function statusBloqueioLogin(?PDO $pdo, string $ip, ?string $login = null): array
{
    if ($pdo === null) return ['bloqueado' => false, 'restante' => 0];
    $restante = 0;
    $consultas = [['ip', $ip]];
    $loginNorm = $login === null ? '' : loginParaContagem($login);
    if ($loginNorm !== '') $consultas[] = ['login', $loginNorm];
    foreach ($consultas as [$coluna, $valor]) {
        // A liberação ocorre quando a falha de posição MAX sai da janela.
        $sql = "SELECT GREATEST(0, :janela - TIMESTAMPDIFF(SECOND, criado_em, NOW())) FROM tentativas_login
                WHERE $coluna = :v AND sucesso = 0 AND criado_em >= DATE_SUB(NOW(), INTERVAL :janela2 SECOND)
                ORDER BY criado_em DESC LIMIT 1 OFFSET " . (LOGIN_MAX_TENTATIVAS - 1);
        $r = comTabelaTentativas($pdo, function () use ($pdo, $sql, $valor) {
            $st = $pdo->prepare($sql);
            $st->execute([':janela' => LOGIN_JANELA_SEGUNDOS, ':v' => $valor, ':janela2' => LOGIN_JANELA_SEGUNDOS]);
            return $st->fetchColumn();
        });
        if ($r !== false) $restante = max($restante, (int) $r);
    }
    return ['bloqueado' => $restante > 0, 'restante' => $restante];
}

/** Compatível com a assinatura original: true se o IP (e o login, se dado) está bloqueado. */
function verificarBloqueioIP(?PDO $pdo, string $ip, ?string $login = null): bool
{
    return statusBloqueioLogin($pdo, $ip, $login)['bloqueado'];
}

/** Texto do bloqueio, sempre coerente com o tempo real que falta. */
function mensagemBloqueioLogin(int $restanteSegundos): string
{
    $min = max(1, (int) ceil($restanteSegundos / 60));
    return 'Acesso temporariamente bloqueado por excesso de tentativas. Tente novamente em ' . $min . ($min === 1 ? ' minuto.' : ' minutos.');
}

/**
 * Reserva uma tentativa de login. Devolve o id da tentativa, ou null se o
 * bloqueio está ativo (nesse caso NADA é gravado, para que quem insiste não
 * prolongue o próprio bloqueio indefinidamente). O trinco por IP impede que
 * requisições simultâneas ultrapassem o limite.
 *
 * @return array{id:?int, restante:int}
 */
function reservarTentativaLogin(PDO $pdo, string $ip, string $login): array
{
    $trinco = 'akron_login_' . sha1($ip);
    $st = $pdo->prepare('SELECT GET_LOCK(?, 3)'); $st->execute([$trinco]);
    if ((int) $st->fetchColumn() !== 1) return ['id' => null, 'restante' => 60];
    try {
        $estado = statusBloqueioLogin($pdo, $ip, $login);
        if ($estado['bloqueado']) return ['id' => null, 'restante' => $estado['restante']];
        $pdo->prepare('INSERT INTO tentativas_login (ip, login, sucesso) VALUES (?,?,0)')->execute([$ip, loginParaContagem($login)]);
        $id = (int) $pdo->lastInsertId();
        if (random_int(1, 50) === 1) { // limpeza ocasional: guarda 30 dias de histórico
            $pdo->exec('DELETE FROM tentativas_login WHERE criado_em < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        }
        return ['id' => $id, 'restante' => 0];
    } finally {
        $r = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $r->execute([$trinco]);
    }
}

/** Marca a tentativa como bem-sucedida (deixa de contar como falha). */
function concluirTentativaLogin(PDO $pdo, int $id): void
{
    $pdo->prepare('UPDATE tentativas_login SET sucesso = 1 WHERE id = ?')->execute([$id]);
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
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare('INSERT INTO auditoria (usuario_id,acao,descricao) VALUES (?,?,?)');
            $stmt->execute([$usuario_id, substr($acao, 0, 80), $descricao]);
        } catch (PDOException $e) {
            // Falha de auditoria é registrada no servidor sem expor detalhes ao usuário.
            error_log('[ÁKRON] Não foi possível persistir auditoria: ' . $e->getMessage());
        }
    }
}

// ─── SESSÃO E PERMISSÕES ────────────────────────────────────────────────────

/**
 * Confere se existe uma sessão autenticada e válida, SEM redirecionar.
 * Aplica o timeout de sessão (2 h de inatividade, 12 h no máximo) e confere o navegador.
 *
 * @return string|null null se a sessão é válida; caso contrário o motivo:
 *                     'sessao' (não logado), 'timeout' ou 'seguranca' (navegador mudou).
 */
function verificarSessao(): ?string
{
    iniciarSessao();

    if (empty($_SESSION['usuario_id'])) {
        return 'sessao';
    }

    // Timeout por INATIVIDADE (renovado a cada requisição) + limite absoluto.
    // Antes o prazo de 2 h era contado só desde o login: quem estava usando o
    // sistema era deslogado mesmo assim.
    $agora = time();
    $ultima = (int) ($_SESSION['ultima_atividade'] ?? strtotime($_SESSION['login_hora'] ?? 'now'));
    $inicio = strtotime($_SESSION['login_hora'] ?? 'now') ?: $agora;
    if (($agora - $ultima) > SESSAO_INATIVIDADE_SEGUNDOS || ($agora - $inicio) > SESSAO_DURACAO_MAXIMA_SEGUNDOS) {
        encerrarSessao();
        return 'timeout';
    }
    $_SESSION['ultima_atividade'] = $agora;

    // O vínculo com o IP foi removido de propósito: em redes móveis, CGNAT,
    // proxies e provedores com mais de uma saída o IP muda no meio da sessão
    // e derrubava o usuário (no log, o mesmo admin alternava entre dois IPs).
    // Como proteção contra roubo de cookie, o cookie já é HttpOnly/SameSite e o
    // id é regenerado no login; aqui só se confere o navegador (User-Agent).
    $assinatura = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (isset($_SESSION['ua_hash']) && !hash_equals($_SESSION['ua_hash'], $assinatura)) {
        encerrarSessao();
        return 'seguranca';
    }
    $_SESSION['ua_hash'] = $_SESSION['ua_hash'] ?? $assinatura;

    // Revogação de conta/perfil vale também para a sessão web já aberta.
    global $pdo;
    require_once __DIR__ . '/../app/config/conexao.php';
    $stmt = $pdo->prepare('SELECT * FROM usuario WHERE id=?');
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
    if (!$usuario || $usuario['status'] !== 'ativo') {
        encerrarSessao();
        return 'sessao';
    }
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_tema'] = $usuario['tema'] ?? 'automatico';
    $_SESSION['usuario_foto'] = $usuario['foto'] ?? null;
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