<?php
/**
 * UsuarioController.php
 * Controller — orquestra o cadastro de usuários
 */

session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../models/Usuario.php';

// Apenas administradores
exigirAdmin();

$sucesso = '';
$erros   = [];
$dados   = ['nome' => '', 'login' => '', 'tipo' => 'usuario', 'status' => 'ativo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Valida CSRF
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $nome   = trim($_POST['nome']   ?? '');
        $login  = trim($_POST['login']  ?? '');
        $senha  = $_POST['senha']       ?? '';
        $conf   = $_POST['confirmar']   ?? '';
        $tipo   = $_POST['tipo']        ?? 'usuario';
        $status = $_POST['status']      ?? 'ativo';

        $dados = compact('nome', 'login', 'tipo', 'status');

        $usuario = new Usuario($pdo);

        // Validações via Model
        $erros = array_merge($erros, $usuario->validarNome($nome));

        if (!validarLogin($login)) {
            $erros[] = 'Login inválido (3-50 chars, apenas letras, números, ponto, hífen, underscore).';
        }

        $erros = array_merge($erros, $usuario->validarTipo($tipo));
        $erros = array_merge($erros, $usuario->validarStatus($status));

        $erros_senha = validarSenha($senha);
        if (!empty($erros_senha)) {
            $erros = array_merge($erros, $erros_senha);
        }
        if ($senha !== $conf) {
            $erros[] = 'As senhas não coincidem.';
        }

        // Verifica duplicidade de login
        if (empty($erros) && $usuario->loginExiste($login)) {
            $erros[] = "O login '$login' já está em uso.";
        }

        // Tudo ok: persiste via Model
        if (empty($erros)) {
            $novo_id = $usuario->criar($nome, $login, $senha, $tipo, $status);

            if (function_exists('registrarLog')) {
                registrarLog($pdo, $_SESSION['usuario_id'], 'CADASTRO_USUARIO',
                    "Novo usuário: $login (ID $novo_id) - Tipo: $tipo");
            }

            $sucesso = "Usuário <strong>" . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . "</strong> cadastrado com sucesso.";
            $dados   = ['nome' => '', 'login' => '', 'tipo' => 'usuario', 'status' => 'ativo'];
        }
    }
}

$csrf_token = gerarCSRF();

// Carrega a View
require __DIR__ . '/../views/usuarios/cadastro.php';