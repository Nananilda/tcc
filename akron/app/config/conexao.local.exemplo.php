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
    'host' => 'localhost',
    'porta' => '3306',
    'banco' => 'athenasd_akron',
    'usuario' => 'athenasd_athenasdev',
    'senha' => 'Athenas@123',
    // OPCIONAL — chave (mín. 16 caracteres) que os sensores enviam no cabeçalho
    // "X-Chave-Sensor" para registrar leituras em api/leituras.php. Sem ela,
    // apenas administradores logados registram leituras.
    // 'chave_sensores' => 'troque-por-uma-chave-longa-e-secreta',
];