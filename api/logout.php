<?php
/**
 * POST api/logout.php
 * Encerra a sessão do token enviado. Retorna: { sucesso: true }
 */

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

encerrarSessao();
jsonResponse(['sucesso' => true, 'mensagem' => 'Sessão encerrada.']);
