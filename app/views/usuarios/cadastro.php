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
    <title>Cadastro de Usuários </title>
</head>
<body>

<!-- Navegação -->
<div>
    <div>
        <div></div>
       
        <div></div>
        <div>
            Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
       
    </div>
</div>

<main>

    <div>
       
        <h1>CADASTRO DE USUÁRIO</h1>
    </div>

    <?php if ($sucesso): ?>
        <div>
            ✔ <?php echo $sucesso; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($erros)): ?>
        <div>
            <span>⚠</span>
            <div>
                Corrija os seguintes erros:
                <ul>
                    <?php foreach ($erros as $e): ?>
                        <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

        <div>

            <!-- Dados pessoais -->
            <div>
                <div>DADOS DO USUÁRIO</div>
                <div>
                    <div>
                        <div>
                            <label>Nome Completo <span>*</span></label>
                            <input 
                                type="text" 
                                name="nome" 
                                maxlength="100" 
                                placeholder="Ex: João da Silva"
                                value="<?php echo htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8'); ?>" 
                                required
                            >
                        </div>
                    </div>
                    <div>
                        <div>
                            <label>Login <span>*</span></label>
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
            </div>

            <!-- Senha -->
            <div>
                <div>CREDENCIAL DE ACESSO</div>
                <div>
                    <div>
                        <div>
                            <label>Senha <span>*</span></label>
                            <input 
                                type="password" 
                                name="senha" 
                                maxlength="128"
                                placeholder="Mín. 8 chars" 
                                required
                            >
                        </div>
                    </div>
                    <div>
                        <div>
                            <label>Confirmar Senha <span>*</span></label>
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
            </div>

            <!-- Acesso e status -->
            <div>
                <div>NÍVEL DE ACESSO</div>
                <div>
                    <div>
                        <div>
                            <label>Tipo de Usuário <span>*</span></label>
                            <select name="tipo" required>
                                <option value="usuario" <?php echo $dados['tipo'] === 'usuario' ? 'selected' : ''; ?>>Funcionário</option>
                                <option value="admin" <?php echo $dados['tipo'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                            <div>
                                <?php if ($dados['tipo'] === 'funcionario'): ?>
                                    Acesso: <strong>Visualização</strong> de sensores, gráficos e relatórios.
                                <?php else: ?>
                                    Acesso: <strong>Total</strong> — inclui gestão de usuários, sensores e configurações do sistema.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div>
                            <label>Status da Conta <span>*</span></label>
                            <select name="status" required>
                                <option value="ativo" <?php echo $dados['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo $dados['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option><br>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div>
               
                <button type="submit">CADASTRAR USUÁRIO</button><br><br>

                  <a href="/tcc/app/views/dashboard/painel.php">Voltar ao dashboard</a><br><br>

                 <a href="logout.php">Sair do site</a><br><br>
            </div>

        </div>
    </form>
</main>

</body>
</html>