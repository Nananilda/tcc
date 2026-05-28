<?php

$host = "10.140.169.71";
$banco = "banco_tcc";
$usuario = "root";
$senha = "123456";
$port = "3307";

// bianca
// 123

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


// index.php
// validação de identificação (login/0 e credencial de acesso, criar js
// adicionar if 

// senha: gabi.moura, gabi3A@@
// senha: bianca, 123

// administrador
// confere graficos (1 grafico por sensor) - grafico_sensores.php
// ve relatórios (aplicar filtros de relatórios) - relatorio_qualidade.php
// sensores (ve se o sensor está ativo ou não + adiciona novos sensores) - gestao_sensores.php
// registrar (aplicar validação e tirar o "sair do site") - UsuarioController.php

// funcionário
// confere graficos (1 grafico por sensor) - grafico_sensores.php
// ve relatórios (aplicar filtros de relatórios) - relatorio_qualidade.php
// sensores (ve se o sensor está ativo ou não) - gestao_sensores.php