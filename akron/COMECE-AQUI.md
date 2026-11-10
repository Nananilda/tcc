# ÁKRON — começar com o banco_tcc existente

1. Extraia a pasta `tcc` deste ZIP em `C:\xampp\htdocs\tcc`.
2. No MySQL Workbench, abra `sql/banco_tcc-completar-estrutura.sql` e execute o arquivo inteiro na sua conexão MySQL. Ele usa o `banco_tcc` existente, acrescenta as tabelas e colunas que faltarem e preserva os cadastros e o histórico.
3. Copie `app/config/conexao.local.exemplo.php` para `app/config/conexao.local.php`. Preencha host, porta, banco (`banco_tcc`), usuário e senha do mesmo MySQL usado no Workbench. Se já tem esse arquivo configurado, preserve-o.
4. Inicie o Apache e o MySQL. Abra `http://localhost/tcc/` e entre com sua conta existente.

Execute o SQL também na outra máquina, usando a conexão MySQL daquela instalação, e configure o arquivo local com as credenciais correspondentes.

Para fotos de perfil, habilite a extensão GD no PHP e reinicie o Apache. O projeto requer PHP 8.1 ou superior com `pdo_mysql` e `iconv`.

Detalhes de conexão e cPanel: [INSTALACAO.md](INSTALACAO.md).
Alterações solicitadas: [ALTERACOES.md](ALTERACOES.md).
Testes realizados: [docs/VALIDACAO.md](docs/VALIDACAO.md).
