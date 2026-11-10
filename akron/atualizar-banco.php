<?php
/** Atualiza a instalação existente sem exigir PHP no PATH do Windows. */
define('AKRON_ACESSO_BANCO_LEGADO', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/app/config/conexao.php';
require_once __DIR__ . '/app/services/MigracaoBanco.php';
exigirLogin();
header('Cache-Control: no-store');
$admin = ehAdmin();
$erro = '';
$sucesso = '';
$pendente = MigracaoBanco::pendente($pdo);
if (!$admin) {
    http_response_code(403);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCSRF($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $erro = 'Sua sessão de confirmação expirou. Recarregue a página e tente novamente.';
    } elseif (($_POST['backup_confirmado'] ?? '') !== '1') {
        http_response_code(422);
        $erro = 'Faça o backup do banco e confirme a opção abaixo antes de continuar.';
    } else {
        try {
            set_time_limit(0);
            $alterou = MigracaoBanco::executar($pdo);
            $pendente = MigracaoBanco::pendente($pdo);
            $sucesso = $alterou ? 'Atualização concluída. Seus cadastros, senhas e histórico foram preservados.' : 'O banco já está atualizado.';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            http_response_code(503);
            error_log('[ÁKRON migração] ' . $e->getMessage());
            $erro = 'Não foi possível concluir a atualização. Consulte o log de erros do Apache no XAMPP para verificar o motivo. Depois de corrigir, você pode tentar novamente. O script não apaga seus registros.';
        }
    }
}
$tituloPagina = 'Atualização do banco';
require __DIR__ . '/app/views/partials/head.php';
?>
<body>
<main class="card" style="max-width:760px;margin:40px auto;padding:28px;width:calc(100% - 32px);box-sizing:border-box">
    <h1>Atualização do banco de dados</h1>
    <?php if (!$admin): ?>
        <p>O banco precisa ser atualizado por um administrador antes de continuar usando o sistema.</p>
        <p><a class="btn" href="<?= escapar(urlApp('routes/logout.php')) ?>">Sair e entrar como administrador</a></p>
    <?php else: ?>
        <?php if ($erro): ?><p class="msg-erro" role="alert"><?= escapar($erro) ?></p><?php endif; ?>
        <?php if ($sucesso): ?><p class="msg-sucesso" role="status"><?= escapar($sucesso) ?></p><?php endif; ?>
        <?php if ($pendente): ?>
            <p>Esta instalação possui uma estrutura de banco anterior à versão do sistema. Vamos acrescentar os campos e vínculos necessários para os estoques, sensores e alertas.</p>
            <p>Banco que será atualizado: <strong><?= escapar((string) $pdo->query('SELECT DATABASE()')->fetchColumn()) ?></strong>.</p>
            <ol>
                <li>No phpMyAdmin, selecione esse banco, clique em <strong>Exportar</strong> e salve o backup SQL.</li>
                <li>Interrompa o envio dos sensores e peça aos demais usuários para aguardar.</li>
                <li>Confirme abaixo e clique em <strong>Atualizar banco</strong>. Aguarde a conclusão.</li>
            </ol>
            <p>Os cadastros e as senhas existentes serão mantidos. Os sensores e o histórico antigos serão associados ao <strong>Estoque inicial</strong>. Se houver uma interrupção, a atualização pode ser retomada.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= escapar(gerarCSRF()) ?>">
                <p><label><input type="checkbox" name="backup_confirmado" value="1" required> Salvei o backup e interrompi o uso do sistema durante a atualização.</label></p>
                <button class="btn" type="submit">Atualizar banco</button>
            </form>
        <?php else: ?>
            <p>A estrutura do banco está pronta para esta versão do sistema.</p>
            <a class="btn" href="<?= escapar(urlApp('app/views/dashboard/painel.php')) ?>">Abrir painel</a>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
