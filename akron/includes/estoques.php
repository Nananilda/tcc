<?php
/** Restrição compartilhada por páginas, relatórios e endpoints. */
require_once __DIR__ . '/auth.php';
function escopoEstoque(string $coluna = 's.estoque_id'): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $coluna)) {
        throw new InvalidArgumentException('Coluna de estoque inválida.');
    }
    if (ehAdmin()) return '1=1';
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    return "EXISTS (SELECT 1 FROM usuario_estoque ue WHERE ue.estoque_id = $coluna AND ue.usuario_id = $usuarioId)";
}
function podeAcessarEstoque(PDO $pdo, int $estoqueId): bool
{
    if ($estoqueId <= 0) return false;
    $stmt = $pdo->prepare('SELECT e.id FROM estoques e WHERE e.id = ? AND ' . escopoEstoque('e.id'));
    $stmt->execute([$estoqueId]);
    return (bool) $stmt->fetchColumn();
}
function exigirEstoque(PDO $pdo, int $estoqueId): void
{
    if (podeAcessarEstoque($pdo, $estoqueId)) return;
    if (defined('API_CONTEXT')) jsonResponse(['sucesso'=>false,'mensagem'=>'Estoque indisponível ou sem permissão.'], 403);
    http_response_code(403);
    require __DIR__ . '/../app/views/403.php';
    exit;
}
