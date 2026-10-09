<?php
/**
 * app/config/conexao.php
 * Conexão única com o MySQL/MariaDB (banco_tcc), usada por todo o backend
 * (controllers, views e api/*.php). Ao incluir este arquivo, a variável $pdo
 * fica disponível — ou o script termina com uma mensagem amigável de erro.
 *
 * ── Como configurar o servidor de banco ──────────────────────────────────────
 * Os valores padrão abaixo (localhost / root / 123456) são os do ambiente de
 * desenvolvimento original. Para usar outro servidor SEM editar este arquivo
 * (recomendado — a senha não precisa ir para o Git), use UMA das opções:
 *
 *   1) Variáveis de ambiente:
 *        AKRON_DB_HOST, AKRON_DB_PORT, AKRON_DB_NAME, AKRON_DB_USER, AKRON_DB_PASS
 *
 *   2) Arquivo app/config/conexao.local.php (ignorado pelo Git), que devolve
 *      um array — veja app/config/conexao.local.exemplo.php.
 *
 * As variáveis de ambiente têm prioridade sobre o arquivo local, que tem
 * prioridade sobre os valores padrão.
 *
 * IMPORTANTE: no laboratório da faculdade o endereço do MySQL costuma mudar a
 * cada aula — confirme o host/porta antes de apresentar.
 */

require_once __DIR__ . '/app.php';

/**
 * Reúne as credenciais do banco (padrão → arquivo local → variáveis de ambiente).
 *
 * @return array{host:string,porta:string,banco:string,usuario:string,senha:string}
 */
function configuracaoBanco(): array
{
    $config = [
        'host' => 'localhost',
        'porta' => '3306',
        'banco' => 'banco_tcc',
        'usuario' => 'root',
        'senha' => '',
    ];

    // Arquivo local opcional (não versionado)
    $arquivoLocal = __DIR__ . '/conexao.local.php';
    if (is_file($arquivoLocal)) {
        $local = require $arquivoLocal;
        if (is_array($local)) {
            $config = array_merge($config, array_intersect_key($local, $config));
        }
    }

    // Variáveis de ambiente (maior prioridade)
    $variaveis = [
        'host' => 'AKRON_DB_HOST',
        'porta' => 'AKRON_DB_PORT',
        'banco' => 'AKRON_DB_NAME',
        'usuario' => 'AKRON_DB_USER',
        'senha' => 'AKRON_DB_PASS',
    ];
    foreach ($variaveis as $campo => $nome) {
        $valor = getenv($nome);
        if ($valor !== false && ($valor !== '' || $campo === 'senha')) {
            $config[$campo] = $valor;
        }
    }

    return $config;
}

/**
 * Encerra a requisição informando, sem vazar detalhes técnicos, que o banco
 * está indisponível. Em endpoints da API (API_CONTEXT) responde JSON; nas
 * páginas web exibe uma tela simples e já com a identidade do sistema.
 */
function falhaDeConexao(): never
{
    http_response_code(503);

    if (defined('API_CONTEXT')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Banco de dados indisponível. Tente novamente em instantes.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nome = APP_NOME;
    echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco de dados indisponível — {$nome}</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               font-family:'Segoe UI',Roboto,Arial,sans-serif; background:#0d1218; color:#e9eef3; padding:20px; }
        main { max-width:460px; text-align:center; }
        h1 { font-size:1.25rem; margin:0 0 10px; }
        p { color:#9fb0c0; line-height:1.55; margin:0 0 8px; }
        code { background:#1d2733; border:1px solid #2a3846; border-radius:4px; padding:1px 6px; font-size:.85em; }
    </style>
</head>
<body>
    <main>
        <h1>Não foi possível conectar ao banco de dados</h1>
        <p>O servidor MySQL pode estar desligado ou as credenciais estão incorretas.</p>
        <p>Confira o host, a porta, o usuário e a senha em <code>app/config/conexao.php</code>
           (ou nas variáveis <code>AKRON_DB_*</code>) e recarregue a página.</p>
    </main>
</body>
</html>
HTML;
    exit;
}

// ── Conexão ──────────────────────────────────────────────────────────────────
$config = configuracaoBanco();

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['banco']};port={$config['porta']};charset=utf8mb4",
        $config['usuario'],
        $config['senha'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // erros SQL viram exceções
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // linhas como arrays associativos
            PDO::ATTR_EMULATE_PREPARES => false,               // prepared statements reais (mais seguro)
        ]
    );

    // Alinha o relógio do banco (NOW(), CURRENT_TIMESTAMP) ao fuso do PHP definido
    // em app.php. Sem isso, se o servidor MySQL estiver em outro fuso (ex.: UTC),
    // as datas gravadas ficariam deslocadas em relação ao que as telas exibem.
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    // O motivo detalhado vai só para o log do servidor; o usuário vê um aviso genérico.
    error_log('[' . APP_NOME . '] Falha ao conectar ao banco: ' . $e->getMessage());
    falhaDeConexao();
}

// Não deixa a senha acessível no escopo global das páginas
unset($config);
