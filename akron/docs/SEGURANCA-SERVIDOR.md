# Proteção de arquivos internos

## Apache / LiteSpeed (XAMPP, cPanel)

Já incluída no projeto: `.htaccess` na raiz e em `sql/`, `tests/`, `docs/`, `includes/`, `app/config/`, `app/models/`, `app/controllers/`, `app/services/` e `app/views/partials/`. Requer `AllowOverride All` (padrão do XAMPP e da maioria das hospedagens). Nada a configurar.

Teste rápido (esperado: 403 em todos, exceto o último):

```
curl -I https://SEU-SITE/sql/schema.sql
curl -I https://SEU-SITE/docs/API.md
curl -I https://SEU-SITE/includes/auth.php
curl -I https://SEU-SITE/app/config/conexao.local.php
curl -I https://SEU-SITE/public/assets/css/style.css   # 200
```

## Servidor embutido do PHP (somente desenvolvimento)

```
php -S 127.0.0.1:8000 router-dev.php
```

O `php -S` ignora `.htaccess`; o `router-dev.php` repete a mesma proteção.

## nginx

`.htaccess` não funciona no nginx. Acrescente ao bloco `server`:

```
location ~* ^/(sql|tests|docs|includes|app/config|app/models|app/controllers|app/services|app/views/partials)(/|$) { deny all; }
location ~* \.(md|sql|py|cjs|sh|log|bak|dist|orig|swp|lock)$ { deny all; }
location ~ /\. { deny all; }
```

## Melhor prática em produção

Se a hospedagem permitir, aponte o *document root* para uma pasta que contenha só o necessário ou mantenha `app/config/conexao.local.php` fora da raiz pública (o sistema também lê as variáveis de ambiente `AKRON_DB_*`).

## Limite de tentativas de login

5 falhas em 15 minutos bloqueiam o IP e, separadamente, o login. Só `REMOTE_ADDR` é considerado. **Atrás de proxy reverso/CDN** todos os visitantes chegam com o IP do proxy e seriam tratados como um só IP: nesse cenário é preciso configurar o servidor web para restaurar o IP real (por exemplo `mod_remoteip` no Apache) antes de o PHP ler `REMOTE_ADDR`.
