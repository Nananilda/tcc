<?php
/**
 * app/config/conexao.local.exemplo.php
 * Modelo de configuração local do banco de dados.
 *
 * Como usar:
 *   1) Copie este arquivo para  app/config/conexao.local.php
 *   2) Ajuste os valores abaixo para o seu ambiente.
 *
 * O arquivo conexao.local.php é ignorado pelo Git (veja .gitignore), então a
 * senha do banco NÃO é enviada ao repositório. Você só precisa informar o que
 * for diferente do padrão — as chaves omitidas continuam com o valor original.
 */

return [
    'host' => '127.0.0.1',   // no laboratório, use o IP do servidor MySQL
    'porta' => '3306',
    'banco' => 'banco_tcc',
    'usuario' => 'root',
    'senha' => 'troque-esta-senha',
];
