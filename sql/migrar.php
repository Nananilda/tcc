<?php
/** Migração não destrutiva e retomável. Faça backup antes de executar. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute pelo terminal ou use atualizar-banco.php.'); }
require_once __DIR__ . '/../app/config/conexao.php';
require_once __DIR__ . '/../app/services/MigracaoBanco.php';
try {
    $alterou = MigracaoBanco::executar($pdo);
    echo $alterou ? "Migração concluída: cadastros e histórico preservados.\n" : "Migração já aplicada. Nenhum dado alterado.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migração interrompida. Corrija o erro e execute novamente: " . $e->getMessage() . "\n");
    exit(1);
}
