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
     * @return array{sucesso:bool, usuario:array|null, mensagem:string}
     */
    public function login(string $login, string $senha, string $ip): array
    {
        if (verificarBloqueioIP($this->pdo, $ip)) {
            return [
                'sucesso' => false,
                'usuario' => null,
                'mensagem' => 'Acesso temporariamente bloqueado. Tente novamente em 15 minutos.',
            ];
        }

        $resultado = autenticarUsuario($this->pdo, $login, $senha);

        if ($resultado['sucesso']) {
            $this->criarSessao($resultado['usuario'], $ip);
            registrarLog($this->pdo, (int) $resultado['usuario']['id'], 'LOGIN_SUCESSO', "Login: {$resultado['usuario']['login']} - IP: $ip");
        } else {
            registrarLog($this->pdo, null, 'LOGIN_FALHA', "Login tentado: $login - IP: $ip - Motivo: {$resultado['mensagem']}");
        }

        return [
            'sucesso' => $resultado['sucesso'],
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
        $_SESSION['login_hora'] = date('Y-m-d H:i:s');
        $_SESSION['ip_login'] = $ip;
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
