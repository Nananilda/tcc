<?php
/**
 * app/views/usuarios/cadastro.php — Cadastro de usuário (somente admin).
 *
 * Formulário com validação no servidor (UsuarioController → UsuarioModel):
 * nome, login único, senha forte com confirmação, perfil e status. A senha é
 * gravada como hash bcrypt. Protegido por token CSRF.
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirAdmin();

require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';

$controller = new UsuarioController($pdo, true, (int) $_SESSION['usuario_id']);

$sucesso = '';
$erros = [];
$dados = ['nome' => '', 'login' => '', 'tipo' => 'usuario', 'status' => 'ativo']; // valores do formulário

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido. Recarregue a página.';
    } else {
        // Guarda o que foi digitado para repreencher o formulário em caso de erro
        $dados = [
            'nome' => trim($_POST['nome'] ?? ''),
            'login' => trim($_POST['login'] ?? ''),
            'tipo' => $_POST['tipo'] ?? 'usuario',
            'status' => $_POST['status'] ?? 'ativo',
        ];

        $resposta = $controller->cadastrar($_POST);
        $erros = $resposta['erros'];
        $sucesso = $resposta['mensagem'];

        // Cadastro concluído: limpa o formulário para o próximo
        if ($sucesso !== '') {
            $dados = ['nome' => '', 'login' => '', 'tipo' => 'usuario', 'status' => 'ativo'];
        }
    }
}

$csrf_token = gerarCSRF();

$tituloPagina = 'Cadastro de Usuário';
$secaoTopbar = 'Usuários';
$cssPagina = ['usuarios.css'];
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Cadastro de Usuário</h1>

<?php if ($sucesso): ?>
    <div class="msg-sucesso" role="status"><?php echo escapar($sucesso); ?></div>
<?php endif; ?>

<?php if (!empty($erros)): ?>
    <div class="msg-erro" role="alert">
        Corrija os seguintes erros:
        <ul>
            <?php foreach ($erros as $erro): ?>
                <li><?php echo escapar($erro); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo escapar($csrf_token); ?>">

        <!-- Dados pessoais -->
        <div class="usuarios-secao">
            <div class="usuarios-secao-titulo">Dados do usuário</div>
            <div class="usuarios-grid">
                <div>
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" maxlength="100" placeholder="Ex: João da Silva"
                        value="<?php echo escapar($dados['nome']); ?>" required>
                </div>
                <div>
                    <label for="login">Login *</label>
                    <input type="text" id="login" name="login" maxlength="50" placeholder="joao.silva"
                        value="<?php echo escapar($dados['login']); ?>" pattern="[a-zA-Z0-9._\-]{3,50}" required>
                </div>
            </div>
        </div>

        <!-- Senha -->
        <div class="usuarios-secao">
            <div class="usuarios-secao-titulo">Credencial de acesso</div>
            <div class="usuarios-grid">
                <div>
                    <label for="senha">Senha *</label>
                    <input type="password" id="senha" name="senha" maxlength="128" placeholder="Mín. 8 caracteres" required>
                    <div class="usuarios-dica">Deve conter maiúscula, minúscula, número e caractere especial.</div>
                </div>
                <div>
                    <label for="confirmar">Confirmar Senha *</label>
                    <input type="password" id="confirmar" name="confirmar" maxlength="128" placeholder="Repita a senha" required>
                </div>
            </div>
        </div>

        <!-- Acesso e status -->
        <div class="usuarios-secao">
            <div class="usuarios-secao-titulo">Nível de acesso</div>
            <div class="usuarios-grid">
                <div>
                    <label for="tipo">Tipo de Usuário *</label>
                    <select id="tipo" name="tipo" required>
                        <option value="usuario" <?php echo $dados['tipo'] === 'usuario' ? 'selected' : ''; ?>>
                            <?php echo escapar(rotuloTipoUsuario('usuario')); ?></option>
                        <option value="admin" <?php echo $dados['tipo'] === 'admin' ? 'selected' : ''; ?>>
                            <?php echo escapar(rotuloTipoUsuario('admin')); ?></option>
                    </select>
                    <!-- A dica acompanha a opção escolhida (alternada pelo script abaixo) -->
                    <div class="usuarios-dica" data-dica-tipo="usuario" <?php echo $dados['tipo'] === 'usuario' ? '' : 'hidden'; ?>>
                        Acesso: <strong>Visualização</strong> de sensores, gráficos e relatórios.
                    </div>
                    <div class="usuarios-dica" data-dica-tipo="admin" <?php echo $dados['tipo'] === 'admin' ? '' : 'hidden'; ?>>
                        Acesso: <strong>Total</strong> — inclui gestão de usuários, sensores e configurações do sistema.
                    </div>
                </div>
                <div>
                    <label for="status">Status da Conta *</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?php echo $dados['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo $dados['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>
            </div>
        </div>

        <button type="submit">Cadastrar Usuário</button>
    </form>
</div>

<script>
    // Mostra apenas a dica correspondente ao tipo de usuário selecionado
    document.getElementById('tipo').addEventListener('change', function () {
        document.querySelectorAll('[data-dica-tipo]').forEach(function (dica) {
            dica.hidden = dica.getAttribute('data-dica-tipo') !== this.value;
        }, this);
    });
</script>

<div class="nav-rodape">
    <a href="gestao_usuarios.php">← Voltar à gestão de usuários</a>
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>
