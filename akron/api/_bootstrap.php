<?php
/**
 * api/_bootstrap.php
 * Ponto de entrada comum a todos os endpoints JSON usados pelo app ÁKRON.
 * Cada endpoint (api/login.php, api/sensores.php, ...) começa com:
 *
 *     require_once __DIR__ . '/_bootstrap.php';
 *
 * Isso já deixa disponíveis: a sessão (identificada pelo token), $pdo
 * (conexao.php), as funções de includes/auth.php, os cabeçalhos JSON/CORS e os
 * helpers jsonResponse(), corpoRequisicao(), exigirLoginApi() e exigirAdminApi().
 *
 * ── Autenticação por token ───────────────────────────────────────────────────
 * Em api/login.php o servidor devolve um `token` (o identificador da sessão
 * PHP). O app o envia em todas as chamadas seguintes:
 *
 *     Authorization: Bearer <token>
 *
 * Por que token e não cookie? Um app Flutter rodando no navegador (Flutter
 * Web) não consegue ler/guardar cookies de outro endereço; já o cabeçalho
 * Authorization funciona em Android, iOS, desktop e web. Como a API não usa
 * cookies, ela também não sofre de CSRF.
 *
 * Documentação dos endpoints: veja README.md (seção "API do aplicativo").
 */

define('API_CONTEXT', true);

// ── CORS ─────────────────────────────────────────────────────────────────────
// Libera chamadas do app (mobile/desktop não têm "origem" de navegador; no
// Flutter Web isso permite testar a partir de localhost). Como não há
// cookies/credenciais, o curinga "*" é seguro.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Chave-Sensor');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Pré-voo (preflight) do navegador: responde sem tocar em sessão nem banco.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Encerra o endpoint devolvendo JSON com o código HTTP informado.
 */
function jsonResponse(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

/**
 * Extrai o token do cabeçalho "Authorization: Bearer <token>".
 * Procura em todos os lugares onde servidores diferentes o expõem.
 */
function tokenDaRequisicao(): ?string
{
    $cabecalho = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($cabecalho === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $nome => $valor) {
            if (strcasecmp($nome, 'Authorization') === 0) {
                $cabecalho = $valor;
                break;
            }
        }
    }

    // O formato do identificador de sessão do PHP é restrito: rejeita lixo cedo.
    if (preg_match('/^Bearer\s+([A-Za-z0-9,\-]{16,128})$/i', trim($cabecalho), $m)) {
        return $m[1];
    }
    return null;
}

// ── Sessão identificada pelo token ───────────────────────────────────────────
ini_set('session.use_cookies', '0');      // a API não usa cookies
ini_set('session.use_only_cookies', '0'); // permite definir o id manualmente
ini_set('session.use_strict_mode', '1');  // ignora ids que o servidor não criou (anti session-fixation)
require_once __DIR__ . '/../app/config/app.php';
configurarArmazenamentoSessao(); // pasta e prazo próprios (ver app/config/app.php)

$token = tokenDaRequisicao();
if ($token !== null) {
    session_id($token);
}
session_start();

require_once __DIR__ . '/../app/config/conexao.php';
require_once __DIR__ . '/../includes/auth.php';
set_exception_handler(function(Throwable $erro): never {
    error_log('[ÁKRON API] '.$erro->getMessage());
    jsonResponse(['sucesso'=>false,'mensagem'=>'Não foi possível processar a requisição.'],500);
});
foreach($_GET as $nome=>$valor) if(!is_scalar($valor)) jsonResponse(['sucesso'=>false,'mensagem'=>'Filtro inválido.'],422);


/**
 * Lê o corpo da requisição. O app envia JSON (Content-Type: application/json);
 * formulários simples (form-urlencoded) também são aceitos, para facilitar
 * testes com curl. Sempre devolve um array associativo.
 */
function corpoRequisicao(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $bruto = file_get_contents('php://input');
        $objeto = json_decode($bruto);
        if (!is_object($objeto)) jsonResponse(['sucesso'=>false,'mensagem'=>'Envie um objeto JSON válido.'],422);
        $dados = json_decode($bruto, true);
        return validarCorpoApi($dados);
    }
    return validarCorpoApi($_POST);
}

/**
 * Equivalente de exigirLogin(), mas devolvendo JSON 401 em vez de redirecionar.
 *
 * A cada chamada o usuário é conferido de novo no banco: se a conta foi
 * excluída ou inativada, ou se o perfil (admin/funcionário) mudou, isso vale
 * imediatamente — sem esperar o token expirar.
 */
function exigirLoginApi(): void
{
    global $pdo;

    if (empty($_SESSION['usuario_id'])) {
        jsonResponse(['sucesso' => false, 'mensagem' => 'Sessão não autenticada.'], 401);
    }

    // Mesmas regras do sistema web: 2 h de inatividade ou 12 h desde o login
    $agora = time();
    $ultima = (int) ($_SESSION['ultima_atividade'] ?? strtotime($_SESSION['login_hora'] ?? 'now'));
    $inicio = strtotime($_SESSION['login_hora'] ?? 'now') ?: $agora;
    $_SESSION['ultima_atividade'] = $agora;
    if (($agora - $ultima) > SESSAO_INATIVIDADE_SEGUNDOS || ($agora - $inicio) > SESSAO_DURACAO_MAXIMA_SEGUNDOS) {
        encerrarSessao();
        jsonResponse(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.'], 401);
    }

    $stmt = $pdo->prepare("SELECT tipo, status FROM usuario WHERE id = :id");
    $stmt->execute([':id' => (int) $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();

    if (!$usuario || $usuario['status'] !== 'ativo') {
        encerrarSessao();
        jsonResponse(['sucesso' => false, 'mensagem' => 'Conta indisponível. Faça login novamente.'], 401);
    }
    $_SESSION['usuario_tipo'] = $usuario['tipo']; // reflete mudanças de perfil na hora
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
        jsonResponse(['sucesso' => false, 'mensagem' => 'Acesso negado.', 'erros' => ['Acesso negado.']], 403);
    }
}

/**
 * Converte o resultado de um controller ({mensagem, erros}) na resposta JSON
 * padrão da API: HTTP 200 quando deu certo, 422 quando há erros de validação.
 */
function respostaDoControlador(array $resultado): never
{
    $erros = $resultado['erros'] ?? [];
    jsonResponse(
        ['sucesso' => empty($erros), 'mensagem' => $resultado['mensagem'] ?? '', 'erros' => $erros]
            + array_diff_key($resultado, ['mensagem' => 1, 'erros' => 1]),
        empty($erros) ? 200 : 422
    );
}

/** Tipos inválidos são erros de entrada, nunca casts silenciosos de arrays. */
function validarCorpoApi(array $dados): array
{
    foreach($dados as $campo=>$valor){
        if($campo==='estoque_ids') {
            if(!is_array($valor)||array_filter($valor,fn($id)=>filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)) jsonResponse(['sucesso'=>false,'mensagem'=>'Lista de estoques inválida.'],422);
        } elseif(is_array($valor)||is_object($valor)||is_bool($valor)) jsonResponse(['sucesso'=>false,'mensagem'=>'Campo inválido: '.$campo],422);
        if(in_array($campo,['id','sensor_id','estoque_id'],true)&&filter_var($valor,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false) jsonResponse(['sucesso'=>false,'mensagem'=>'Identificador inválido.'],422);
    }
    return $dados;
}
