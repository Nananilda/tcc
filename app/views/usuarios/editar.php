<?php
session_start();

// Ajuste o caminho conforme sua estrutura de pastas
require_once '../../config/conexao.php';

// Verificar se usuário está logado e é admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Inicializar variáveis
$usuario_encontrado = null;
$busca_realizada = false;
$erros = [];
$sucesso = '';
$dados = [
    'id' => '',
    'nome' => '',
    'login' => '',
    'tipo' => 'usuario',
    'status' => 'ativo'
];

// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Processar busca de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_usuario'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $login_busca = trim($_POST['login_busca']);
        
        if (empty($login_busca)) {
            $erros[] = 'Digite um login para buscar.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM usuario WHERE login = :login");
                $stmt->execute([':login' => $login_busca]);
                $usuario_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);
                $busca_realizada = true;
                
                if ($usuario_encontrado) {
                    $dados = [
                        'id' => $usuario_encontrado['id'],
                        'nome' => $usuario_encontrado['nome'],
                        'login' => $usuario_encontrado['login'],
                        'tipo' => $usuario_encontrado['tipo'],
                        'status' => $usuario_encontrado['status']
                    ];
                } else {
                    $erros[] = 'Usuário não encontrado com este login.';
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao buscar usuário: ' . $e->getMessage();
            }
        }
    }
}

// Processar atualização de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_usuario'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nome = trim($_POST['nome']);
        $login = trim($_POST['login']);
        $tipo = $_POST['tipo'];
        $status = $_POST['status'];
        $senha = trim($_POST['senha']);
        $confirmar_senha = trim($_POST['confirmar_senha']);
        
        if (!$id) {
            $erros[] = 'ID do usuário inválido.';
        }
        
        if (empty($nome)) {
            $erros[] = 'O campo nome é obrigatório.';
        } elseif (strlen($nome) < 3 || strlen($nome) > 100) {
            $erros[] = 'Nome deve ter entre 3 e 100 caracteres.';
        }
        
        if (empty($login)) {
            $erros[] = 'O campo login é obrigatório.';
        } elseif (!preg_match('/^[a-zA-Z0-9._\-]{3,50}$/', $login)) {
            $erros[] = 'Login deve ter entre 3 e 50 caracteres e pode conter letras, números, ponto, underline e hífen.';
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuario WHERE login = :login AND id != :id");
            $stmt->execute([':login' => $login, ':id' => $id]);
            if ($stmt->fetch()) {
                $erros[] = 'Este login já está em uso por outro usuário.';
            }
        } catch (PDOException $e) {
            $erros[] = 'Erro ao verificar login.';
        }
        
        if (!empty($senha)) {
            if (strlen($senha) < 8) {
                $erros[] = 'A senha deve ter no mínimo 8 caracteres.';
            }
            if ($senha !== $confirmar_senha) {
                $erros[] = 'As senhas não conferem.';
            }
        }
        
        if (empty($erros)) {
            try {
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE usuario 
                        SET nome = :nome, 
                            login = :login, 
                            tipo = :tipo, 
                            status = :status,
                            senha = :senha
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':login' => $login,
                        ':tipo' => $tipo,
                        ':status' => $status,
                        ':senha' => $senha_hash,
                        ':id' => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE usuario 
                        SET nome = :nome, 
                            login = :login, 
                            tipo = :tipo, 
                            status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':login' => $login,
                        ':tipo' => $tipo,
                        ':status' => $status,
                        ':id' => $id
                    ]);
                }
                
                $sucesso = 'Usuário atualizado com sucesso!';
                
                $usuario_encontrado['nome'] = $nome;
                $usuario_encontrado['login'] = $login;
                $usuario_encontrado['tipo'] = $tipo;
                $usuario_encontrado['status'] = $status;
                $dados = [
                    'id' => $id,
                    'nome' => $nome,
                    'login' => $login,
                    'tipo' => $tipo,
                    'status' => $status
                ];
                
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
                
            } catch (PDOException $e) {
                $erros[] = 'Erro ao atualizar usuário: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuários</title>
</head>
<body>

<hr>
<table width="100%" cellpadding="10">
    <tr>
        <td>
            <strong>Sistema de Sensores</strong>
        </td>
        <td align="right">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </td>
    </tr>
</table>
<hr>

<h1 align="center">EDITAR USUÁRIO</h1>
<p align="center">Busque um usuário pelo login e edite suas informações</p>
<hr>

<br>

<?php if ($sucesso): ?>
    <table width="100%" cellpadding="10" bgcolor="#d4edda" border="1">
        <tr>
            <td>
                ✔ <?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
    </table>
    <br>
<?php endif; ?>

<?php if (!empty($erros)): ?>
    <table width="100%" cellpadding="10" bgcolor="#f8d7da" border="1">
        <tr>
            <td>
                <strong>⚠ Atenção! Corrija os seguintes erros:</strong>
                <ul>
                    <?php foreach ($erros as $e): ?>
                        <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
    </table>
    <br>
<?php endif; ?>

<fieldset>
    <legend><strong>Buscar Usuário</strong></legend>
    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <table cellpadding="5">
            <tr>
                <td><strong>Login:</strong></td>
                <td>
                    <input 
                        type="text" 
                        name="login_busca" 
                        placeholder="Digite o login do usuário"
                        value="<?php echo isset($_POST['login_busca']) ? htmlspecialchars($_POST['login_busca'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                        required
                        size="30"
                    >
                </td>
                <td>
                    <button type="submit" name="buscar_usuario">BUSCAR</button>
                </td>
            </tr>
        </table>
    </form>
</fieldset>

<br>

<?php if ($usuario_encontrado): ?>
    <fieldset>
        <legend><strong>Editar Usuário</strong></legend>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo $dados['id']; ?>">
            <input type="hidden" name="atualizar_usuario" value="1">
            
            <table cellpadding="8">
                <tr>
                    <td width="150"><strong>ID:</strong></td>
                    <td>
                        <input type="text" value="<?php echo $dados['id']; ?>" readonly size="10">
                    </td>
                </tr>
                <tr>
                    <td><strong>Nome Completo:</strong> <font color="red">*</font></td>
                    <td>
                        <input 
                            type="text" 
                            name="nome" 
                            maxlength="100" 
                            placeholder="Ex: João da Silva"
                            value="<?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?>" 
                            required
                            size="50"
                        >
                    </td>
                </tr>
                <tr>
                    <td><strong>Login:</strong> <font color="red">*</font></td>
                    <td>
                        <input 
                            type="text" 
                            name="login" 
                            maxlength="50" 
                            placeholder="joao.silva"
                            value="<?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?>"
                            pattern="[a-zA-Z0-9._\-]{3,50}" 
                            required
                            size="30"
                        >
                        <br><small>Apenas letras, números, ponto, underline e hífen</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>Nova Senha:</strong></td>
                    <td>
                        <input 
                            type="password" 
                            name="senha" 
                            maxlength="128"
                            placeholder="Deixe em branco para manter a atual"
                            size="30"
                        >
                        <br><small>Mínimo 8 caracteres (preencha apenas se quiser alterar)</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>Confirmar Nova Senha:</strong></td>
                    <td>
                        <input 
                            type="password" 
                            name="confirmar_senha" 
                            maxlength="128"
                            placeholder="Repita a nova senha"
                            size="30"
                        >
                    </td>
                </tr>
                <tr>
                    <td><strong>Tipo de Usuário:</strong> <font color="red">*</font></td>
                    <td>
                        <select name="tipo" required>
                            <option value="usuario" <?php echo $dados['tipo'] === 'usuario' ? 'selected' : ''; ?>>Funcionário</option>
                            <option value="admin" <?php echo $dados['tipo'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        </select>
                        <br>
                        <small>
                            <?php if ($dados['tipo'] === 'usuario'): ?>
                                Acesso: Visualização de sensores, gráficos e relatórios.
                            <?php else: ?>
                                Acesso: Total — inclui gestão de usuários, sensores e configurações do sistema.
                            <?php endif; ?>
                        </small>
                    </td>
                </tr>
                <tr>
                    <td><strong>Status da Conta:</strong> <font color="red">*</font></td>
                    <td>
                        <select name="status" required>
                            <option value="ativo" <?php echo $dados['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo $dados['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                        <br><small>Usuários inativos não podem acessar o sistema.</small>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <hr>
                        <button type="submit">SALVAR ALTERAÇÕES</button>
                        <button type="reset">LIMPAR</button>
                    </td>
                </tr>
            </table>
        </form>
    </fieldset>
<?php elseif ($busca_realizada && !$usuario_encontrado): ?>
    <table width="100%" cellpadding="15" bgcolor="#f8d7da" border="1">
        <tr>
            <td align="center">
                <strong>⚠ Nenhum usuário encontrado com este login!</strong>
                <br><br>
                Verifique o login digitado.
            </td>
        </tr>
    </table>
<?php elseif (!$busca_realizada): ?>
    <table width="100%" cellpadding="15" bgcolor=>
       
    </table>
<?php endif; ?>

<br>
<a href="../usuarios/gestao_usuarios.php">Voltar a gestão cadastro</a><br><br>

<a href="../dashboard/painel.php">← Voltar ao dashboard</a><br>


<hr>


</body>
</html>
