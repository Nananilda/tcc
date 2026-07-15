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
$confirmar_exclusao = false;
$dados = [
    'id' => '',
    'nome' => '',
    'login' => '',
    'tipo' => '',
    'status' => ''
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
                    // Verificar se é o próprio usuário tentando se excluir
                    if ($usuario_encontrado['id'] == $_SESSION['usuario_id']) {
                        $erros[] = 'Você não pode excluir seu próprio usuário.';
                        $usuario_encontrado = null;
                    } else {
                        $dados = [
                            'id' => $usuario_encontrado['id'],
                            'nome' => $usuario_encontrado['nome'],
                            'login' => $usuario_encontrado['login'],
                            'tipo' => $usuario_encontrado['tipo'],
                            'status' => $usuario_encontrado['status']
                        ];
                    }
                } else {
                    $erros[] = 'Usuário não encontrado com este login.';
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao buscar usuário: ' . $e->getMessage();
            }
        }
    }
}

// Processar exclusão de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_usuario'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        if (!$id) {
            $erros[] = 'ID do usuário inválido.';
        }
        
        // Verificar se não é o próprio usuário
        if ($id == $_SESSION['usuario_id']) {
            $erros[] = 'Você não pode excluir seu próprio usuário.';
        }
        
        if (empty($erros)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM usuario WHERE id = :id");
                $stmt->execute([':id' => $id]);
                
                if ($stmt->rowCount() > 0) {
                    $sucesso = 'Usuário excluído com sucesso!';
                    $usuario_encontrado = null;
                    $busca_realizada = false;
                    $confirmar_exclusao = false;
                    
                    // Regenerar token CSRF
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_token'];
                } else {
                    $erros[] = 'Usuário não encontrado para exclusão.';
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao excluir usuário: ' . $e->getMessage();
            }
        }
    }
}

// Processar confirmação de exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao'])) {
    $confirmar_exclusao = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Usuários</title>
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

<h1 align="center">EXCLUIR USUÁRIO</h1>
<p align="center">Busque um usuário pelo login e exclua suas informações</p>
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

<?php if ($usuario_encontrado && !$confirmar_exclusao): ?>
    <fieldset>
        <legend><strong>Usuário Encontrado</strong></legend>
        <table cellpadding="8" border="1">
            <tr>
                <td width="150"><strong>ID:</strong></td>
                <td><?php echo $dados['id']; ?></td>
            </tr>
            <tr>
                <td><strong>Nome:</strong></td>
                <td><?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <tr>
                <td><strong>Login:</strong></td>
                <td><?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <tr>
                <td><strong>Tipo:</strong></td>
                <td>
                    <?php 
                    if ($dados['tipo'] === 'admin') {
                        echo 'Administrador';
                    } else {
                        echo 'Funcionário';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <?php 
                    if ($dados['status'] === 'ativo') {
                        echo '<font color="green">Ativo</font>';
                    } else {
                        echo '<font color="red">Inativo</font>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="confirmar_exclusao" value="1">
                        <button type="submit" style="background-color: #ff9800; color: black;">CONTINUAR PARA EXCLUSÃO</button>
                        <a href="excluir.php"><button type="button">CANCELAR</button></a>
                    </form>
                 </td>
            </tr>
        </table>
    </fieldset>
<?php elseif ($usuario_encontrado && $confirmar_exclusao): ?>
    <fieldset>
        <legend><strong>Confirmar Exclusão</strong></legend>
        <table width="100%" cellpadding="10" bgcolor="#f8d7da" border="1">
            <tr>
                <td align="center">
                    <strong>⚠ ATENÇÃO! Você está prestes a excluir o seguinte usuário:</strong>
                    <br><br>
                    <table align="center" cellpadding="5">
                        <tr>
                            <td><strong>ID:</strong></td>
                            <td><?php echo $dados['id']; ?></td>
                         </tr>
                        <tr>
                            <td><strong>Nome:</strong></td>
                            <td><?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                         </tr>
                        <tr>
                            <td><strong>Login:</strong></td>
                            <td><?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?></td>
                         </tr>
                    </table>
                    <br>
                    <strong><font color="red">Esta ação é irreversível!</font></strong>
                    <br><br>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id" value="<?php echo $dados['id']; ?>">
                        <input type="hidden" name="excluir_usuario" value="1">
                        <button type="submit" style="background-color: #f44336; color: white;">SIM, EXCLUIR USUÁRIO</button>
                        <a href="excluir.php"><button type="button">NÃO, CANCELAR</button></a>
                    </form>
                </td>
            </tr>
        </table>
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
    <table width="100%" cellpadding="15" bgcolor>
       
     </table>
<?php endif; ?>

<br>
<a href="../usuarios/gestao_usuarios.php">Voltar a gestão cadastro</a><br><br>
<a href="../dashboard/painel.php">← Voltar ao dashboard</a><br>



</body>
</html>
