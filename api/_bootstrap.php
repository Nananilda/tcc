<?php
/**
 * api/_bootstrap.php
 * Ponto de entrada comum a todos os endpoints JSON usados pelo app Flutter.
 * Cada endpoint (api/login.php, api/sensores.php, ...) começa com:
 *
 *     require_once __DIR__ . '/_bootstrap.php';
 *
 * Isso já deixa disponíveis: sessão iniciada, $pdo (conexao.php), as
 * funções de includes/auth.php, cabeçalhos JSON/CORS e os helpers
 * jsonResponse(), corpoRequisicao(), exigirLoginApi() e exigirAdminApi().
 */

define('API_CONTEXT', true);

if (session_status() === PHP_SESSION_NONE) {
    // Necessário para o app Flutter (cliente HTTP) enviar/receber o cookie
    // de sessão como se fosse um navegador comum.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── CORS ─────────────────────────────────────────────────────────────────
// Libera chamadas do app (mobile/desktop não têm "origem" de navegador;
// no Flutter Web isso permite testar a partir de localhost).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../app/config/conexao.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Encerra o endpoint devolvendo JSON com o código HTTP informado.
 */
function jsonResponse(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lê o corpo da requisição tanto em JSON (Content-Type: application/json,
 * usado pelo app Flutter) quanto em form-urlencoded (compatibilidade com
 * os formulários HTML antigos), sempre devolvendo um array associativo.
 */
function corpoRequisicao(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $bruto = file_get_contents('php://input');
        $dados = json_decode($bruto, true);
        return is_array($dados) ? $dados : [];
    }
    return $_POST;
}

/**
 * Equivalente de exigirLogin(), mas devolvendo JSON 401 em vez de redirecionar.
 */
function exigirLoginApi(): void
{
    if (empty($_SESSION['usuario_id'])) {
        jsonResponse(['sucesso' => false, 'mensagem' => 'Sessão não autenticada.'], 401);
    }

    $timeout = 7200;
    if (isset($_SESSION['login_hora']) && (time() - strtotime($_SESSION['login_hora'])) > $timeout) {
        encerrarSessao();
        jsonResponse(['sucesso' => false, 'mensagem' => 'Sessão expirada.'], 401);
    }
}

/**
 * Equivalente de exigirAdmin(), mas devolvendo JSON 403 em vez de redirecionar.
 * Usado por todos os endpoints de GESTÃO DE SENSORES (cadastro/status) e
 * GESTÃO DE USUÁRIOS — usuário comum nunca deve conseguir chamar essas ações.
 */
function exigirAdminApi(): void
{
    exigirLoginApi();
    if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
        jsonResponse(['sucesso' => false, 'mensagem' => 'Acesso negado.'], 403);
    }
}
