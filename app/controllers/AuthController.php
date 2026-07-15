<?php
/**
 * AuthController.php
 *
 * Wrapper orientado a objeto para as funções de autenticação já existentes
 * em includes/auth.php. O login funcional do sistema continua sendo feito
 * em index.php (fluxo procedural, já testado); esta classe apenas organiza
 * a mesma lógica em formato de controller, para quem quiser reaproveitar
 * a autenticação em outro ponto de entrada sem duplicar regras.
 *
 * Não substitui nem altera o comportamento de index.php.
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
     * Autentica um usuário, delegando para autenticarUsuario() de auth.php.
     * Retorna ['sucesso' => bool, 'usuario' => array|null, 'mensagem' => string].
     */
    public function login(string $login, string $senha, string $ip): array
    {
        if (verificarBloqueioIP($this->pdo, $ip)) {
            return [
                'sucesso'  => false,
                'usuario'  => null,
                'mensagem' => 'Acesso temporariamente bloqueado. Tente novamente em 15 minutos.',
            ];
        }

        $resultado = autenticarUsuario($this->pdo, $login, $senha);

        if ($resultado['sucesso']) {
            $this->criarSessao($resultado['usuario'], $ip);
            registrarLog($this->pdo, $resultado['usuario']['id'], 'LOGIN_SUCESSO', "Login: {$resultado['usuario']['login']} - IP: $ip");
        } else {
            registrarLog($this->pdo, null, 'LOGIN_FALHA', "Login tentado: $login - IP: $ip - Motivo: {$resultado['mensagem']}");
        }

        return [
            'sucesso'  => $resultado['sucesso'],
            'usuario'  => $resultado['usuario'] ?? null,
            'mensagem' => $resultado['mensagem'] ?? '',
        ];
    }

    /**
     * Popula a $_SESSION da mesma forma que index.php faz após um login bem-sucedido.
     */
    private function criarSessao(array $usuario, string $ip): void
    {
        session_regenerate_id(true);

        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        $_SESSION['login_hora']   = date('Y-m-d H:i:s');
        $_SESSION['ip_login']     = $ip;
        $_SESSION['csrf_token']   = bin2hex(random_bytes(32));
    }

    /**
     * Encerra a sessão atual, delegando para encerrarSessao() de auth.php.
     */
    public function logout(): void
    {
        encerrarSessao();
    }
}
