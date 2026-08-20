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
    <title>Cadastro de Usuários — IndustrialOS</title>
    <link rel="stylesheet" href="../../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../../public/assets/css/usuarios.css">
</head>
<body>

    <div class="topbar">
        <div class="marca">IndustrialOS — Usuários</div>
        <div class="usuario-info">
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
    </div>

    <div class="container">

        <h1>Cadastro de Usuário</h1>

        <?php if ($sucesso): ?>
            <div class="msg-sucesso">
                ✔ <?php echo $sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <div class="msg-erro">
                Corrija os seguintes erros:
                <ul>
                    <?php foreach ($erros as $e): ?>
                        <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                <!-- Dados pessoais -->
                <div class="usuarios-secao">
                    <div class="usuarios-secao-titulo">Dados do usuário</div>
                    <div class="usuarios-grid">
                        <div>
                            <label>Nome Completo *</label>
                            <input
                                type="text"
                                name="nome"
                                maxlength="100"
                                placeholder="Ex: João da Silva"
                                value="<?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div>
                            <label>Login *</label>
                            <input
                                type="text"
                                name="login"
                                maxlength="50"
                                placeholder="joao.silva"
                                value="<?php echo htmlspecialchars($dados['login'], ENT_QUOTES, 'UTF-8'); ?>"
                                pattern="[a-zA-Z0-9._\-]{3,50}"
                                required
                            >
                        </div>
                    </div>
                </div>

                <!-- Senha -->
                <div class="usuarios-secao">
                    <div class="usuarios-secao-titulo">Credencial de acesso</div>
                    <div class="usuarios-grid">
                        <div>
                            <label>Senha *</label>
                            <input
                                type="password"
                                name="senha"
                                maxlength="128"
                                placeholder="Mín. 8 caracteres"
                                required
                            >
                            <div class="usuarios-dica">Deve conter maiúscula, minúscula, número e caractere especial.</div>
                        </div>
                        <div>
                            <label>Confirmar Senha *</label>
                            <input
                                type="password"
                                name="confirmar"
                                maxlength="128"
                                placeholder="Repita a senha"
                                required
                            >
                        </div>
                    </div>
                </div>

                <!-- Acesso e status -->
                <div class="usuarios-secao">
                    <div class="usuarios-secao-titulo">Nível de acesso</div>
                    <div class="usuarios-grid">
                        <div>
                            <label>Tipo de Usuário *</label>
                            <select name="tipo" required>
                                <option value="usuario" <?php echo $dados['tipo'] === 'usuario' ? 'selected' : ''; ?>>Funcionário</option>
                                <option value="admin" <?php echo $dados['tipo'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                            <div class="usuarios-dica">
                                <?php if ($dados['tipo'] === 'usuario'): ?>
                                    Acesso: <strong>Visualização</strong> de sensores, gráficos e relatórios.
                                <?php else: ?>
                                    Acesso: <strong>Total</strong> — inclui gestão de usuários, sensores e configurações do sistema.
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label>Status da Conta *</label>
                            <select name="status" required>
                                <option value="ativo" <?php echo $dados['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo $dados['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit">Cadastrar Usuário</button>
            </form>
        </div>

        <div class="nav-rodape">
            <a href="gestao_usuarios.php">← Voltar à gestão de usuários</a>
            <a href="../dashboard/painel.php">← Voltar ao painel</a>
        </div>

    </div>

</body>
</html>
