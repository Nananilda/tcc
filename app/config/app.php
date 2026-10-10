<?php
/**
 * app/config/app.php
 * Configurações gerais do sistema ÁKRON que NÃO dependem do banco de dados:
 * nome da aplicação, fuso horário, URL base de instalação (BASE_URL) e os
 * helpers de visão usados por todas as páginas (urlApp, escapar, jsonParaScript
 * e rotuloTipoUsuario).
 *
 * É carregado por includes/auth.php e por app/config/conexao.php; portanto,
 * qualquer página que use autenticação ou banco já tem estas definições.
 */

// ── Identidade do sistema ────────────────────────────────────────────────────
// Use SEMPRE estas constantes para exibir o nome — assim ele fica padronizado
// em todas as telas e basta alterá-lo aqui.
if (!defined('APP_NOME')) {
    define('APP_NOME', 'ÁKRON');
    define('APP_DESCRICAO', 'Sistema de Monitoramento Industrial');
    define('APP_LEMA', 'Visão de ponta a ponta para sua operação');
    define('APP_VERSAO', '2.0');
}

// ── Fuso horário ─────────────────────────────────────────────────────────────
// Sem isto o PHP usa o fuso do php.ini (normalmente UTC) e a "Última
// atualização" do painel apareceria com 3 horas de diferença no Brasil.
// Para mudar, defina a variável de ambiente AKRON_TIMEZONE.
date_default_timezone_set(getenv('AKRON_TIMEZONE') ?: 'America/Sao_Paulo');

// ── BASE_URL ─────────────────────────────────────────────────────────────────
// Caminho (a partir da raiz do servidor web) onde o projeto está instalado.
// Exemplos: ''  (http://localhost/)  ou  '/tcc'  (http://localhost/tcc/).
// É usado para montar links de CSS/JS/imagens e redirecionamentos, de modo que
// o sistema funcione em qualquer pasta sem editar nada.
//
// Ordem de decisão:
//   1) variável de ambiente AKRON_BASE_URL (para casos especiais);
//   2) detecção automática comparando o caminho do script atual no disco com
//      o caminho dele na URL (funciona com Apache, XAMPP, Alias e php -S);
//   3) detecção pelo DOCUMENT_ROOT;
//   4) raiz ('').
if (!defined('BASE_URL')) {
    define('BASE_URL', detectarBaseUrl());
}

/**
 * Descobre o BASE_URL conforme a ordem descrita acima.
 */
function detectarBaseUrl(): string
{
    $manual = getenv('AKRON_BASE_URL');
    if ($manual !== false && $manual !== '') {
        return '/' . trim($manual, '/');
    }

    // Raiz do projeto no disco (esta pasta é app/config → sobe dois níveis)
    $raizProjeto = realpath(__DIR__ . '/../..');
    if ($raizProjeto === false) {
        return '';
    }
    $raizProjeto = str_replace('\\', '/', $raizProjeto);

    // 2) script atual: caminho no disco x caminho na URL
    $scriptDisco = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    $scriptUrl = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($scriptDisco !== false && $scriptUrl !== '') {
        $scriptDisco = str_replace('\\', '/', $scriptDisco);
        if (stripos($scriptDisco, $raizProjeto) === 0) {
            $relativo = substr($scriptDisco, strlen($raizProjeto)); // ex.: /app/views/dashboard/painel.php
            $tamanho = strlen($relativo);
            if ($tamanho > 0 && strcasecmp(substr($scriptUrl, -$tamanho), $relativo) === 0) {
                return rtrim(substr($scriptUrl, 0, strlen($scriptUrl) - $tamanho), '/');
            }
        }
    }

    // 3) DOCUMENT_ROOT
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($docRoot !== false) {
        $docRoot = str_replace('\\', '/', $docRoot);
        if (stripos($raizProjeto, $docRoot) === 0) {
            return rtrim(substr($raizProjeto, strlen($docRoot)), '/');
        }
    }

    return '';
}

// ── Helpers de visão ─────────────────────────────────────────────────────────

/**
 * Monta uma URL absoluta (a partir da raiz do servidor) dentro do projeto.
 * Ex.: urlApp('public/assets/css/style.css') → '/tcc/public/assets/css/style.css'
 */
function urlApp(string $caminho = ''): string
{
    return BASE_URL . '/' . ltrim($caminho, '/');
}

/**
 * Escapa um valor para exibição segura em HTML (previne XSS).
 * Use em TODA saída que venha do banco, de $_GET/$_POST ou da sessão.
 *
 * @param mixed $valor
 */
function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Rótulo amigável do tipo de usuário ('admin' → 'Administrador').
 * Centraliza o texto para que todas as telas mostrem o mesmo nome.
 */
function rotuloTipoUsuario(?string $tipo): string
{
    return $tipo === 'admin' ? 'Administrador' : 'Funcionário';
}

/**
 * Converte um valor PHP em JSON seguro para ser escrito DENTRO de um <script>
 * (as flags HEX_* impedem que dados com "</script>", aspas ou "&" quebrem a
 * página ou injetem código).
 *
 * @param mixed $valor
 */
function jsonParaScript($valor): string
{
    return json_encode(
        $valor,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION
    );
}

/**
 * Interpreta um número digitado/enviado pelo usuário ou por um sensor.
 * Aceita int, float e texto numérico — inclusive com vírgula decimal
 * ("36,4") —, arredonda para 2 casas (precisão das colunas DECIMAL(10,2)) e
 * devolve null se o valor não for um número finito dentro do limite da coluna.
 *
 * @param mixed $valor
 */
function lerNumero($valor): ?float
{
    if (is_string($valor)) {
        $valor = str_replace(',', '.', trim($valor));
        if ($valor === '') {
            return null;
        }
    }
    if (!is_int($valor) && !is_float($valor) && !(is_string($valor) && is_numeric($valor))) {
        return null;
    }

    $numero = round((float) $valor, 2);
    return (is_finite($numero) && abs($numero) <= 99999999.99) ? $numero : null;
}

/**
 * Chave secreta que sensores/gateways enviam no cabeçalho "X-Chave-Sensor" ao
 * registrar leituras em api/leituras.php (POST). Assim um dispositivo pode
 * enviar dados sem ter uma conta de usuário.
 *
 * Defina-a (mínimo de 16 caracteres) pela variável de ambiente
 * AKRON_CHAVE_SENSORES ou pela chave 'chave_sensores' de
 * app/config/conexao.local.php. Se não estiver definida (ou for curta demais),
 * devolve '' e o recurso fica DESLIGADO — só administradores logados registram
 * leituras.
 */
function chaveDosSensores(): string
{
    $chave = getenv('AKRON_CHAVE_SENSORES');

    if ($chave === false || $chave === '') {
        $arquivoLocal = __DIR__ . '/conexao.local.php';
        $local = is_file($arquivoLocal) ? require $arquivoLocal : null;
        $chave = (is_array($local) && is_string($local['chave_sensores'] ?? null)) ? $local['chave_sensores'] : '';
    }

    return strlen($chave) >= 16 ? $chave : '';
}
