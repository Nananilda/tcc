<?php
/**
 * POST api/login.php
 * Body: { "login": "...", "senha": "..." }
 * Retorna: { sucesso, usuario: {id, nome, login, tipo}, mensagem }
 *
 * Em caso de sucesso, o servidor mantém uma sessão (cookie PHPSESSID) —
 * o app Flutter precisa reenviar esse cookie nas próximas chamadas
 * (é o que ApiService.dart faz automaticamente).
 */

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$dados = corpoRequisicao();
$login = trim($dados['login'] ?? '');
$senha = $dados['senha'] ?? '';
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (verificarBloqueioIP($pdo, $ip)) {
    jsonResponse([
        'sucesso'  => false,
        'mensagem' => 'Acesso temporariamente bloqueado. Tente novamente em 15 minutos.',
    ], 429);
}

$resultado = autenticarUsuario($pdo, $login, $senha);

if (!$resultado['sucesso']) {
    registrarLog($pdo, null, 'LOGIN_FALHA', "Login tentado: $login - IP: $ip - Motivo: {$resultado['mensagem']}");
    jsonResponse(['sucesso' => false, 'mensagem' => $resultado['mensagem']], 401);
}

$usuario = $resultado['usuario'];

session_regenerate_id(true);
$_SESSION['usuario_id']   = $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_tipo'] = $usuario['tipo'];
$_SESSION['login_hora']   = date('Y-m-d H:i:s');
$_SESSION['ip_login']     = $ip;
$_SESSION['csrf_token']   = bin2hex(random_bytes(32));

registrarLog($pdo, $usuario['id'], 'LOGIN_SUCESSO', "Login: {$usuario['login']} - IP: $ip");

jsonResponse([
    'sucesso'  => true,
    'usuario'  => $usuario,
    'mensagem' => 'Login realizado com sucesso.',
]);
