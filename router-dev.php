<?php
/**
 * router-dev.php — roteador para o servidor embutido do PHP (uso local/desenvolvimento).
 *   php -S 127.0.0.1:8000 router-dev.php
 * O servidor embutido ignora .htaccess; este roteador repete a mesma proteção:
 * pastas internas e arquivos não públicos respondem 403.
 */
$caminho = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$caminho = '/' . ltrim(preg_replace('#/+#', '/', str_replace('\\', '/', $caminho)), '/');
$bloqueado = '#^/(sql|tests|docs|includes|app/config|app/models|app/controllers|app/services|app/views/partials)(/|$)#i';
$arquivoOculto = '#(^|/)\.[^/]+$|\.(md|sql|py|cjs|sh|log|bak|dist|orig|swp|lock)$#i';
if (str_contains($caminho, '..') || preg_match($bloqueado, $caminho) || preg_match($arquivoOculto, $caminho) || $caminho === '/router-dev.php') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Acesso negado.');
}
return false; // demais requisições: comportamento padrão do servidor embutido
