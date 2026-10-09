<?php
/**
 * POST api/login.php
 * Body: { "login": "...", "senha": "..." }
 * Retorna: { sucesso, mensagem, usuario: {id, nome, login, tipo, status, criado_em}, token }
 *
 * Em caso de sucesso devolve o `token` que o app deve enviar nas próximas
 * chamadas, no cabeçalho "Authorization: Bearer <token>" (ver _bootstrap.php).
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$dados = corpoRequisicao();
$login = trim($dados['login'] ?? '');
$senha = (string) ($dados['senha'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// O mesmo fluxo de login da tela web (bloqueio por IP, validação, sessão e log)
$resultado = (new AuthController($pdo))->login($login, $senha, $ip);

if (!$resultado['sucesso']) {
    jsonResponse(['sucesso' => false, 'mensagem' => $resultado['mensagem']], 401);
}

jsonResponse([
    'sucesso' => true,
    'mensagem' => 'Login realizado com sucesso.',
    'usuario' => $resultado['usuario'],
    'token' => session_id(),
]);
