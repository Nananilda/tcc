<?php
/**
 * GET api/dashboard.php
 * Retorna o resumo exibido no painel principal.
 * { sucesso, resumo: { sensores_ativos, sensores_total, alertas_pendentes, ultima_atualizacao } }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();

require_once __DIR__ . '/../app/controllers/DashbordController.php';

$controller = new DashbordController($pdo);
jsonResponse(['sucesso' => true, 'resumo' => $controller->obterResumo()]);
