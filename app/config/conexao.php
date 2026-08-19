<?php
/**
 * app/config/conexao.php
 * Conexão única com o MySQL (banco_tcc), usada por todo o backend
 * (controllers e views em app/*).
 *
 * Mantém os mesmos parâmetros de servidor já em uso; apenas corrige o
 * charset (utf8mb4, compatível com as tabelas InnoDB/utf8mb4 do projeto)
 * e deixa a conexão mais robusta (erros sempre como exceção, resultados
 * já tipados, sem emulação de prepared statements).
 *
 * IMPORTANTE: ajuste $host/$port abaixo para o endereço real do MySQL
 * no seu ambiente (o valor abaixo é o do laboratório da faculdade e
 * muda a cada aula — confirme com o professor/rede antes de rodar).
 */

$host   = "10.140.169.14"; // verificar toda aula
$banco  = "banco_tcc";
$usuario = "root";
$senha  = "123456";
$port   = "3306";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$banco;port=$port;charset=utf8mb4",
        $usuario,
        $senha,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
