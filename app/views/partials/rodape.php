<?php
/**
 * app/views/partials/rodape.php
 * Fecha o <main> aberto por cabecalho.php, exibe a assinatura da marca e
 * encerra o documento HTML.
 *
 * Variável opcional definida pela página antes do require:
 *   $scriptsRodape  array  Scripts a carregar no fim do <body> (caminhos relativos
 *                          a public/assets/js), depois das bibliotecas do <head>.
 */

$scriptsRodape = $scriptsRodape ?? [];
?>
    </main>

    <footer class="rodape-marca">
        <span class="logo logo-simbolo" aria-hidden="true"></span>
        <span><?php echo escapar(APP_NOME . ' · ' . APP_DESCRICAO . ' · v' . APP_VERSAO); ?></span>
    </footer>

    <?php foreach ($scriptsRodape as $script): ?>
        <script src="<?php echo urlApp('public/assets/js/' . $script); ?>"></script>
    <?php endforeach; ?>

</body>

</html>
