<?php
// Impede acesso direto à view — deve ser carregada pelo UsuarioController
if (!isset($dados, $csrf_token)) {
    header('Location: ../../controllers/UsuarioController.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuários</title>
</head>
<body>

<div>
    Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
    | <a href="/tcc/app/views/dashboard/painel.php">Voltar ao painel</a>
    | <a href="/tcc/routes/logout.php">Logout</a>
</div>

<h1>CADASTRO DE USUÁRIO</h1>

<?php if ($sucesso): ?>
    <div style="color:green;">✔ <?php echo $sucesso; ?></div>
<?php endif; ?>

<?php if (!empty($erros)): ?>
    <div style="color:red;">
        <span>⚠ Corrija os seguintes erros:</span>
        <ul>
            <?php foreach ($erros as $e): ?>
                <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="/tcc/app/controllers/UsuarioController.php" autocomplete="off" id="formCadastro">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Dados pessoais -->
    <fieldset>
        <legend>Dados do Usuário</legend>

        <label>Nome Completo *<br>
            <input
                type="text"
                name="nome"
                maxlength="100"
                placeholder="Ex: João da Silva"
                value="<?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                required
            >
        </label><br><br>

        <label>Login *<br>
            <input
                type="text"
                name="login"
                maxlength="50"
                placeholder="joao.silva"
                value="<?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?>"
                pattern="[a-zA-Z0-9._\-]{3,50}"
                title="Apenas letras, números, ponto, hífen ou underscore (3–50 chars)"
                required
                id="campo_login"
            >
            <span id="erro_login" style="color:red;font-size:0.85em;"></span>
        </label>
    </fieldset>

    <!-- Credencial -->
    <fieldset>
        <legend>Credencial de Acesso</legend>

        <label>Senha * (mín. 8 chars, maiúscula, número e especial)<br>
            <input
                type="password"
                name="senha"
                id="campo_senha"
                maxlength="128"
                placeholder="Mín. 8 chars"
                required
            >
            <span id="forca_senha" style="font-size:0.85em;"></span>
        </label><br><br>

        <label>Confirmar Senha *<br>
            <input
                type="password"
                name="confirmar"
                id="campo_confirmar"
                maxlength="128"
                placeholder="Repita a senha"
                required
            >
            <span id="erro_confirmar" style="color:red;font-size:0.85em;"></span>
        </label>
    </fieldset>

    <!-- Nível de acesso -->
    <fieldset>
        <legend>Nível de Acesso</legend>

        <label>Tipo de Usuário *<br>
            <select name="tipo" required>
                <option value="usuario" <?php echo $dados['tipo'] === 'usuario' ? 'selected' : ''; ?>>Funcionário</option>
                <option value="admin"   <?php echo $dados['tipo'] === 'admin'   ? 'selected' : ''; ?>>Administrador</option>
            </select>
        </label><br><br>

        <label>Status da Conta *<br>
            <select name="status" required>
                <option value="ativo"   <?php echo $dados['status'] === 'ativo'   ? 'selected' : ''; ?>>Ativo</option>
                <option value="inativo" <?php echo $dados['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </label>
    </fieldset>

    <br>
    <button type="submit">CADASTRAR USUÁRIO</button>
</form>

<script>
// Validação de login
document.getElementById('campo_login').addEventListener('blur', function () {
    const re = /^[a-zA-Z0-9._\-]{3,50}$/;
    const span = document.getElementById('erro_login');
    if (!re.test(this.value.trim())) {
        span.textContent = 'Use apenas letras, números, ponto, hífen ou underscore (3–50 chars).';
    } else {
        span.textContent = '';
    }
});

// Indicador de força de senha
document.getElementById('campo_senha').addEventListener('input', function () {
    const s = this.value;
    let forca = 0;
    if (s.length >= 8)              forca++;
    if (/[A-Z]/.test(s))           forca++;
    if (/[0-9]/.test(s))           forca++;
    if (/[\W_]/.test(s))           forca++;

    const labels = ['', 'Fraca', 'Média', 'Boa', 'Forte'];
    const cores  = ['', 'red', 'orange', 'goldenrod', 'green'];
    const span   = document.getElementById('forca_senha');
    span.textContent = forca > 0 ? ' Força: ' + labels[forca] : '';
    span.style.color = cores[forca] || '';
});

// Confirmação de senha
document.getElementById('campo_confirmar').addEventListener('blur', function () {
    const span = document.getElementById('erro_confirmar');
    if (this.value !== document.getElementById('campo_senha').value) {
        span.textContent = 'As senhas não coincidem.';
    } else {
        span.textContent = '';
    }
});

// Bloqueia envio se houver erros visíveis
document.getElementById('formCadastro').addEventListener('submit', function (e) {
    const erros = document.querySelectorAll('#erro_login, #erro_confirmar');
    for (const el of erros) {
        if (el.textContent.trim() !== '') {
            e.preventDefault();
            alert('Corrija os erros antes de enviar.');
            return;
        }
    }
});
</script>

</body>
</html>