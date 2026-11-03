<?php
/**
 * app/views/sensores/gestao_sensores.php — Gestão de Sensores (página inicial).
 * Menu de acesso às ações de sensores: consultar a lista (todos os usuários)
 * e cadastrar/editar/excluir (somente administradores).
 */

require_once __DIR__ . '/../../../includes/auth.php';
exigirLogin();

require_once __DIR__ . '/../partials/icones.php';

$ehAdmin = ehAdmin();

// Ações do menu: [ícone, título, descrição, página]
$acoes = [
   ['lista', 'Listar Sensores', 'Consultar todos os sensores cadastrados (somente leitura).', 'listar_sensores.php'],
];
if ($ehAdmin) {
   $acoes[] = ['cadastrar_sensor', 'Cadastrar Sensor', 'Cadastrar sensores.', 'criar_sensor.php'];
    $acoes[] = ['editar', 'Editar Sensores', 'Editar sensores.', 'editar_sensores.php'];
    $acoes[] = ['excluir', 'Excluir Sensores', 'Excluir sensores.', 'excluir_sensores.php'];
}

$tituloPagina = 'Gestão de Sensores';
$secaoTopbar = 'Sensores';
$cssPagina = ['usuarios.css']; // reaproveita .menu-item / .usuarios-menu-lista (ou copie essas regras para sensores.css)
require __DIR__ . '/../partials/cabecalho.php';
?>

<h1>Gestão de Sensores</h1>
<p class="usuarios-dica">Escolha uma das ações abaixo.</p>

<nav class="usuarios-menu-lista" aria-label="Ações de gestão de sensores">
    <?php foreach ($acoes as [$icone, $titulo, $descricao, $pagina]): ?>
        <a class="menu-item" href="<?php echo escapar($pagina); ?>">
            <span class="icone"><?php echo iconeSvg($icone); ?></span>
            <span>
                <strong><?php echo escapar($titulo); ?></strong><br>
                <span class="usuarios-dica"><?php echo escapar($descricao); ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</nav>

<div class="nav-rodape">
    <a href="../dashboard/painel.php">← Voltar ao painel</a>
</div>

<?php require __DIR__ . '/../partials/rodape.php'; ?>