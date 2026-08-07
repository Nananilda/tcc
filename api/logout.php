<?php
/**
 * POST api/logout.php
 * Encerra a sessão atual. Retorna: { sucesso: true }
 */

require_once __DIR__ . '/_bootstrap.php';

encerrarSessao();
jsonResponse(['sucesso' => true, 'mensagem' => 'Sessão encerrada.']);
