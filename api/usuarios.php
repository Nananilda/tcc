<?php
/**
 * api/usuarios.php — gestão de cadastro de usuários (SOMENTE ADMIN).
 *
 * GET  ?login=xxx      -> busca um usuário pelo login  → { sucesso, usuario|null }
 * GET  (sem query)     -> lista todos os usuários      → { sucesso, usuarios: [...] }
 * POST acao=cadastrar  { nome, login, senha, confirmar, tipo, status }
 * POST acao=atualizar  { id, nome, login, tipo, status, senha?, confirmar_senha? }
 * POST acao=excluir    { id }
 *   POST → { sucesso, mensagem, erros }
 *
 * O hash da senha nunca é devolvido.
 */

require_once __DIR__ . '/_bootstrap.php';
exigirAdminApi();

require_once __DIR__ . '/../app/controllers/UsuarioController.php';

$controller = new UsuarioController($pdo, true, (int) $_SESSION['usuario_id']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['login']) && trim($_GET['login']) !== '') {
        $usuario = $controller->buscarPorLogin($_GET['login']);
        jsonResponse(['sucesso' => (bool) $usuario, 'usuario' => $usuario ?: null], $usuario ? 200 : 404);
    }
    jsonResponse(['sucesso' => true, 'usuarios' => $controller->listar()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$dados = corpoRequisicao();

switch ($dados['acao'] ?? '') {
    case 'cadastrar':
        respostaDoControlador($controller->cadastrar($dados));

    case 'atualizar':
        respostaDoControlador($controller->atualizar($dados));

    case 'excluir':
        respostaDoControlador($controller->excluir((int) ($dados['id'] ?? 0)));

    default:
        jsonResponse(['sucesso' => false, 'mensagem' => 'Ação desconhecida.', 'erros' => ['Ação desconhecida.']], 400);
}
