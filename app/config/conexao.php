<?php
/**
 * app/config/conexao.php
 * Conexão única com o MySQL (banco_tcc), usada por todo o backend
 * (páginas antigas em app/views/*.php e os endpoints JSON em api/*.php).
 *
 * Mantém os mesmos parâmetros de servidor já em uso; apenas corrige o
 * charset (utf8mb4, compatível com as tabelas InnoDB/utf8mb4 do projeto)
 * e deixa a conexão mais robusta (erros sempre como exceção, resultados
 * já tipados, sem emulação de prepared statements).
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
    // Endpoints da API (api/*.php) tratam a ausência de conexão retornando
    // JSON de erro; páginas antigas (app/views/*.php) continuam parando aqui.
    if (defined('API_CONTEXT') && API_CONTEXT === true) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso'  => false,
            'mensagem' => 'Erro de conexão com o banco de dados.',
        ]);
        exit;
    }
    die("Erro de conexão: " . $e->getMessage());
}
