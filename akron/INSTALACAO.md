# ÁKRON — conexão com o banco de dados

## Usar o banco_tcc que você já tem

1. Extraia a pasta `tcc` do ZIP. No XAMPP, coloque-a em `C:\xampp\htdocs\tcc`.
2. Inicie o MySQL e conecte-se a ele pelo MySQL Workbench.
3. Abra `sql/banco_tcc-completar-estrutura.sql` e execute o arquivo inteiro. Ao concluir, ele mostra **Estrutura do banco_tcc pronta para o projeto.**
4. Copie `app/config/conexao.local.exemplo.php` para `app/config/conexao.local.php` e ajuste:

   ```php
   <?php
   return [
       'host' => '127.0.0.1',
       'porta' => '3306',
       'banco' => 'banco_tcc',
       'usuario' => 'root',
       'senha' => 'SUA_SENHA_DO_MYSQL',
   ];
   ```

   Use os mesmos dados da conexão do Workbench. Se o seu MySQL local usa senha vazia, informe `''`. Preserve seu arquivo de conexão se ele já está configurado.

5. Inicie o Apache e acesse `http://localhost/tcc/`. Entre com seu login e senha existentes.

O SQL acrescenta a estrutura ausente e os vínculos necessários ao sistema. Mantém senhas, cadastros, leituras e alertas. Pode ser executado novamente. Sensores antigos sem estoque recebem o estoque inicial, preservando o acesso dos usuários antigos.

Na outra máquina, repita os passos usando o banco e as credenciais daquela instalação.

## Requisitos do PHP

Use PHP 8.1 ou superior com `pdo_mysql`, `iconv` e GD; `mbstring` é recomendado. Para habilitar fotos no XAMPP, abra PHP (php.ini), retire o `;` de `;extension=gd` e reinicie o Apache. As fotos são validadas, redimensionadas e guardadas no banco.

As variáveis de ambiente `AKRON_DB_HOST`, `AKRON_DB_PORT`, `AKRON_DB_NAME`, `AKRON_DB_USER` e `AKRON_DB_PASS`, quando definidas, têm prioridade sobre o arquivo local.

## Conexão no cPanel

Use no arquivo `app/config/conexao.local.php` o host informado pela hospedagem, a porta, o nome completo do banco e o usuário MySQL autorizado. O cPanel costuma acrescentar um prefixo, como `conta_banco_tcc` e `conta_usuario`.

Se precisar completar as tabelas de um banco existente no cPanel, ajuste a linha `USE banco_tcc;` de `sql/banco_tcc-completar-estrutura.sql` para o nome completo daquele banco antes de executá-lo na conexão correspondente.

## Somente para um banco novo e vazio

`sql/schema.sql` contém a estrutura completa para uma instalação nova no Workbench. `sql/schema-cpanel.sql` contém a estrutura completa para executar com um banco vazio do cPanel já selecionado. Nessa instalação nova, a conta inicial é `admin` com senha `Admin@123`; altere a senha em Minha conta.

Para o seu `banco_tcc` existente, use o arquivo `sql/banco_tcc-completar-estrutura.sql` descrito no início.

## Sensores reais

Defina uma chave própria de pelo menos 16 caracteres em `chave_sensores`, no arquivo local, ou em `AKRON_CHAVE_SENSORES`. O gateway envia o cabeçalho `X-Chave-Sensor` em `POST /api/leituras.php`, com `sensor_id` e `valor`. Veja [docs/API.md](docs/API.md). Sem essa chave, apenas um administrador autenticado pode registrar leituras. Ajuste o prazo de comunicação à frequência do sensor. Os horários seguem `America/Sao_Paulo`.

## Problemas de conexão

- **Banco indisponível:** confira se o MySQL está iniciado e se host, porta, banco, usuário e senha correspondem à conexão do Workbench.
- **could not find driver:** habilite `pdo_mysql` no PHP usado pelo Apache.
- **Tabela ou coluna inexistente:** execute o SQL complementar inteiro no mesmo banco definido em `conexao.local.php`.
- **Usuário não vê estoques:** vincule a conta aos estoques na edição de usuários.
- **Sem comunicação:** envie uma leitura ou conecte o gateway e revise o prazo configurado no sensor.

## Servidor local alternativo

Com PHP e MySQL instalados, execute na pasta `tcc`:

```sh
php -S 127.0.0.1:8000 -t .
```

Acesse `http://127.0.0.1:8000/`. Esse servidor é destinado a desenvolvimento local.
