# ÁKRON — Monitoramento ambiental de estoques

Projeto PHP + MySQL/MariaDB atualizado a partir do ZIP fornecido e das solicitações de `alterações tcc (1).docx`. Mantém a arquitetura, os perfis administrador/usuário, a autenticação com sessão, a API JSON e o histórico do sistema original. As regras novas estão descritas em [ALTERACOES.md](ALTERACOES.md).

**Comece por [COMECE-AQUI.md](COMECE-AQUI.md).** Execute `sql/banco_tcc-completar-estrutura.sql` no Workbench e configure a conexão com o seu `banco_tcc` existente.

## Requisitos

- PHP **8.1 ou superior**, com `pdo_mysql`, `iconv` e **GD** (fotos); `mbstring` recomendado.
- MySQL 5.7+ ou MariaDB 10.3+ (validado com PHP 8.3 e MariaDB 11.4).
- Apache/XAMPP/WAMP ou servidor local `php -S` para desenvolvimento.
- Navegador atual. Fontes, ícones e Chart.js já estão no projeto; sem npm, Composer ou CDN para executar.

## Funcionalidades

- Estoques: cadastro, edição, desativação, resumo e página individual.
- Sensores: estoque, tipo, localização, quatro limites ambientais e prazo de comunicação configuráveis; histórico preservado nas transferências.
- Usuários: permissões por estoque, aplicadas nas páginas, consultas e API; administradores visualizam todos.
- Monitoramento: temperatura, umidade, qualidade do ar, ruído, pressão e UV, com gráficos separados por unidade. Consulta histórica e atualização automática a cada 15 segundos.
- Alertas: geração por leitura, deduplicação de irregularidades contínuas, reconhecimento, normalização automática e resolução com observações.
- Relatórios: filtros por estoque/sensor/tipo/período/valor, estatísticas, estimativa conservadora de tempo fora dos limites, gráficos e histórico paginado. CSV e PDF contêm **todos** os registros correspondentes aos filtros.
- Interface consistente, responsiva, em temas claro e escuro. Minha conta na barra lateral, com nome, senha, foto e tema claro/escuro/automático salvo individualmente.
- Auditoria das ações administrativas na tabela `auditoria`.

## Estrutura

```text
api/                 Endpoints JSON e exportações autenticadas
app/config/          Configuração e conexão PDO
app/controllers/     Validação e regras compartilhadas por web/API
app/models/          Consultas preparadas e escopo de estoque
app/services/        Geração de CSV/PDF sem serviços externos
app/views/           Páginas PHP e componentes compartilhados
includes/            Sessão, CSRF, permissões e auditoria
public/assets/       CSS, JavaScript, fontes, ícones e Chart.js local
routes/              Atualização periódica para a sessão web
sql/                 SQL da estrutura e demonstração opcional
tests/               Testes de integração da API
docs/                Documentação e capturas
```

As rotas `.php` existentes foram mantidas para evitar quebrar os acessos do projeto original. Os nomes centrais do menu são Painel, Estoques, Monitoramento, Alertas, Relatórios e Usuários; Sensores e Minha conta têm acessos próprios na barra lateral.

## Documentação

- [Instalar ou atualizar](INSTALACAO.md)
- [Mudanças, regras e critérios de entrega](ALTERACOES.md)
- [API e integração dos sensores](docs/API.md)
- [Validação realizada](docs/VALIDACAO.md)

O código Flutter não estava no material anexado; esta entrega contém o sistema web e a API adaptada. A integração com hardware e o aplicativo externo deve usar o contrato documentado em `docs/API.md`.

## Licenças

Mantidas as licenças de Chart.js, Inter e Montserrat nos respectivos diretórios de `public/assets`. A marca e os arquivos de identidade visual originais foram preservados. As capturas em `docs/capturas` pertencem à versão anterior recebida; as capturas anteriores estão em `docs/capturas-v2` e as da revisão atual em `docs/capturas-v3`.
