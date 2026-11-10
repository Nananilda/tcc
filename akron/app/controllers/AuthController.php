<?php
/**
 * app/controllers/AuthController.php
 * Controller: AuthController
 * Orquestra o login e o logout: confere o bloqueio por IP, valida as
 * credenciais (autenticarUsuario, de includes/auth.php), cria a sessão e
 * registra o log da tentativa.
 *
 * É a ÚNICA implementação do fluxo de login, usada tanto pela tela web
 * (index.php) quanto pela API (api/login.php) — assim as duas portas de
 * entrada obedecem às mesmas regras.
 */

require_once __DIR__ . '/../../includes/auth.php';

class AuthController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Autentica um usuário e, se der certo, abre a sessão.
     *
     * @return array{sucesso:bool, bloqueado:bool, usuario:array|null, mensagem:string}
     */
    public function login(string $login, string $senha, string $ip): array
    {
        // Reserva a tentativa antes de conferir a senha (ver includes/auth.php).
        try {
            $reserva = $this->pdo ? reservarTentativaLogin($this->pdo, $ip, $login) : ['id' => null, 'restante' => 0];
        } catch (Throwable $e) {
            error_log('[ÁKRON] Falha no controle de tentativas: ' . $e->getMessage());
            return ['sucesso' => false, 'usuario' => null, 'bloqueado' => false,
                'mensagem' => 'Não foi possível verificar o acesso agora. Tente novamente em instantes.'];
        }
        if ($reserva['id'] === null) {
            registrarLog($this->pdo, null, 'LOGIN_BLOQUEADO', "Login: " . loginParaContagem($login) . " - IP: $ip");
            return ['sucesso' => false, 'usuario' => null, 'bloqueado' => true,
                'mensagem' => mensagemBloqueioLogin($reserva['restante'])];
        }

        $resultado = autenticarUsuario($this->pdo, $login, $senha);

        if ($resultado['sucesso']) {
            concluirTentativaLogin($this->pdo, $reserva['id']);
            $this->criarSessao($resultado['usuario'], $ip);
            registrarLog($this->pdo, (int) $resultado['usuario']['id'], 'LOGIN_SUCESSO', "Login: {$resultado['usuario']['login']} - IP: $ip");
        } else {
            registrarLog($this->pdo, null, 'LOGIN_FALHA', "Login tentado: $login - IP: $ip - Motivo: {$resultado['mensagem']}");
        }

        return [
            'sucesso' => $resultado['sucesso'],
            'bloqueado' => false,
            'usuario' => $resultado['usuario'] ?? null,
            'mensagem' => $resultado['mensagem'] ?? '',
        ];
    }

    /**
     * Popula a $_SESSION após um login bem-sucedido.
     * A sessão é regenerada para evitar "session fixation" (um atacante fixar
     * previamente o identificador de sessão da vítima).
     */
    private function criarSessao(array $usuario, string $ip): void
    {
        iniciarSessao();
        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        $_SESSION['usuario_tema'] = $usuario['tema'] ?? 'automatico';
        $_SESSION['usuario_foto'] = $usuario['foto'] ?? null;
        $_SESSION['login_hora'] = date('Y-m-d H:i:s');
        $_SESSION['ip_login'] = $ip; // só para auditoria; não derruba a sessão se mudar
        $_SESSION['ultima_atividade'] = time();
        $_SESSION['ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Encerra a sessão atual, delegando para encerrarSessao() de auth.php.
     */
    public function logout(): void
    {
        encerrarSessao();
    }
}
