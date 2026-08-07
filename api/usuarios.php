<?php
/**
 * api/usuarios.php — gestão de cadastro de usuários (SOMENTE ADMIN).
 *
 * GET  ?login=xxx    -> busca um usuário pelo login
 * GET  (sem query)   -> lista todos os usuários
 * POST acao=cadastrar  { nome, login, senha, confirmar, tipo, status }
 * POST acao=atualizar  { id, nome, login, tipo, status, senha?, confirmar_senha? }
 * POST acao=excluir    { id }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirAdminApi();

require_once __DIR__ . '/../app/models/Usuario.php';
$model = new Usuario($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['login'])) {
        $usuario = $model->buscarPorLogin(trim($_GET['login']));
        jsonResponse(['sucesso' => (bool) $usuario, 'usuario' => $usuario ?: null]);
    }
    jsonResponse(['sucesso' => true, 'usuarios' => $model->listarTodos()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$dados = corpoRequisicao();
$acao  = $dados['acao'] ?? '';
$erros = [];
$mensagem = '';

if ($acao === 'cadastrar') {
    $nome   = trim($dados['nome']   ?? '');
    $login  = trim($dados['login']  ?? '');
    $senha  = $dados['senha']       ?? '';
    $conf   = $dados['confirmar']   ?? '';
    $tipo   = $dados['tipo']        ?? 'usuario';
    $status = $dados['status']      ?? 'ativo';

    $erros = array_merge($erros, $model->validarNome($nome));
    if (!validarLogin($login)) {
        $erros[] = 'Login inválido (3-50 chars, apenas letras, números, ponto, hífen, underscore).';
    }
    $erros = array_merge($erros, $model->validarTipo($tipo));
    $erros = array_merge($erros, $model->validarStatus($status));
    $erros = array_merge($erros, validarSenha($senha));
    if ($senha !== $conf) {
        $erros[] = 'As senhas não coincidem.';
    }
    if (empty($erros) && $model->loginExiste($login)) {
        $erros[] = "O login '$login' já está em uso.";
    }

    if (empty($erros)) {
        $novoId = $model->criar($nome, $login, $senha, $tipo, $status);
        registrarLog($pdo, $_SESSION['usuario_id'], 'CADASTRO_USUARIO', "Novo usuário: $login (ID $novoId) - Tipo: $tipo");
        $mensagem = "Usuário \"$login\" cadastrado com sucesso.";
    }

} elseif ($acao === 'atualizar') {
    $id     = (int) ($dados['id'] ?? 0);
    $nome   = trim($dados['nome']  ?? '');
    $login  = trim($dados['login'] ?? '');
    $tipo   = $dados['tipo']       ?? 'usuario';
    $status = $dados['status']     ?? 'ativo';
    $senha  = $dados['senha']            ?? '';
    $conf   = $dados['confirmar_senha']  ?? '';

    if ($id <= 0 || !$model->buscarPorId($id)) {
        $erros[] = 'Usuário não encontrado.';
    }
    if (strlen($nome) < 3) {
        $erros[] = 'Nome deve ter no mínimo 3 caracteres.';
    }
    if (!validarLogin($login)) {
        $erros[] = 'Login inválido.';
    }
    if (empty($erros) && $model->loginExiste($login, $id)) {
        $erros[] = "O login '$login' já está em uso.";
    }
    if ($senha !== '' || $conf !== '') {
        $erros = array_merge($erros, validarSenha($senha));
        if ($senha !== $conf) {
            $erros[] = 'As senhas não coincidem.';
        }
    }

    if (empty($erros)) {
        $model->atualizar($id, $nome, $login, $tipo, $status, $senha ?: null);
        registrarLog($pdo, $_SESSION['usuario_id'], 'ATUALIZACAO_USUARIO', "Usuário ID $id atualizado");
        $mensagem = 'Usuário atualizado com sucesso.';
    }

} elseif ($acao === 'excluir') {
    $id = (int) ($dados['id'] ?? 0);

    if ($id === (int) $_SESSION['usuario_id']) {
        $erros[] = 'Você não pode excluir seu próprio usuário.';
    } elseif (!$model->buscarPorId($id)) {
        $erros[] = 'Usuário não encontrado.';
    } else {
        $model->excluir($id);
        registrarLog($pdo, $_SESSION['usuario_id'], 'EXCLUSAO_USUARIO', "Usuário ID $id excluído");
        $mensagem = 'Usuário excluído com sucesso.';
    }

} else {
    $erros[] = 'Ação desconhecida.';
}

jsonResponse([
    'sucesso'  => empty($erros),
    'mensagem' => $mensagem,
    'erros'    => $erros,
]);
