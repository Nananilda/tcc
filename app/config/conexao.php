<?php

$host = "10.140.170.170";
$banco = "banco_tcc";
$usuario = "root";
$senha = "123456";
$port = "3307";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$banco;port=$port;charset=utf8",
        $usuario,
        $senha
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Erro de conexão: " . $e->getMessage());
}
