# API ÁKRON 2

Base: `/tcc/api` no XAMPP ou `/api` se o projeto for a raiz do servidor. Respostas JSON usam `sucesso`, `mensagem` e, quando aplicável, `erros`. As rotas `.php` originais foram preservadas. Envie JSON com `Content-Type: application/json`.

## Autenticação

`POST login.php` com `{"login":"seu-login","senha":"sua-senha"}` retorna `token` e `usuario` (sem hash). Nas chamadas seguintes envie `Authorization: Bearer <token>`. `POST logout.php` encerra a sessão. A conta e o perfil são revalidados a cada chamada; permissões por estoque são aplicadas nas consultas. Sessões duram até duas horas. A API não autentica por cookie; as páginas web continuam usando sessão e CSRF.

`GET status.php` verifica a conexão. `OPTIONS` atende preflight. Métodos não suportados retornam 405; falta de sessão 401; gestão por usuário comum 403; entradas inválidas 422; recurso não disponível pode retornar 404. Uma listagem filtrada sem acesso nunca retorna os dados restritos.

## Recursos

| Endpoint | GET | POST (administrador) |
|---|---|---|
| `estoques.php` | Todos os autorizados; `?id=N` consulta individual | `acao=cadastrar/atualizar/desativar` |
| `sensores.php` | `?estoque_id=N` ou `?id=N`; inclui limites, comunicação e último valor | `acao=cadastrar/atualizar/toggle_status/excluir` (excluir preservado como alias de desativação) |
| `usuarios.php` | Listagem ou `?login=nome`, somente admin | `acao=cadastrar/atualizar/desativar`; alias `excluir` |
| `leituras.php` | Histórico agregado e dados atuais | Registro de leitura: administrador ou gateway com chave |
| `alertas.php` | Lista paginada e contagens dos filtros | `acao=criar/reconhecer/resolver` |
| `dashboard.php` | Resumo e condições atuais; `?estoque_id=N` | — |
| `relatorio.php` | Histórico, estatísticas, alertas, períodos e exportações | — |

### Estoques e usuários

Estoque: `nome`, `descricao`, `localizacao`, `status` (`ativo`/`inativo`); edição usa `id`. Desativação exige `confirmacao:"desativar"`.

Usuário: campos originais `nome`, `login`, `senha`, `confirmar`, `tipo`, `status`, mais `estoque_ids:[1,2]`. Na edição use `id`; senha opcional com `confirmar_senha`. `estoque_ids:[]` revoga todos os vínculos; omitir a chave na API de edição preserva os existentes. Administrador vê todos os estoques independentemente desses vínculos. Contas e sensores são desativados, preservando históricos.

### Sensores

Campos: `nome`, `tipo`, `estoque_id`, `localizacao`, `status`, `intervalo_comunicacao` (1 a 86400 segundos), `limite_critico_min`, `limite_atencao_min`, `limite_atencao_max`, `limite_critico_max`. Limites podem ser nulos; os preenchidos seguem a ordem crescente acima. Edição usa `sensor_id`. Valores iguais ao limite não o ultrapassam.

Tipos mantidos: `temperatura` (°C), `umidade` (%), `qualidade_ar` (AQI), `ruido` (dB), `pressao` (hPa), `uv` (índice). Sensores físicos precisam enviar valores calibrados na unidade correspondente; o sistema não converte sinais elétricos/brutos em AQI.

### Receber leituras

```http
POST /tcc/api/leituras.php
Content-Type: application/json
X-Chave-Sensor: <chave configurada localmente>

{"sensor_id":1,"valor":36.5}
```

O horário é registrado pelo servidor. O estoque é obtido do cadastro do sensor (não é confiado ao remetente). Sensor e estoque precisam estar ativos. A mesma transação grava leitura, estoque original, limites usados, classificação, última comunicação e eventual alerta. Uma irregularidade contínua mantém uma ocorrência; normalização permite uma nova ocorrência futura. Chave inválida não concede acesso.

### Histórico e relatórios

Filtros: `estoque_id`, `sensor_id`, `sensor` (tipo), `data_ini`, `data_fim` (`YYYY-MM-DD`). Em `leituras.php`, sem datas, `horas=6/12/24/48/168` (padrão 24). `pontos` inclui identificação de sensor e estoque, média do intervalo, quantidade de amostras e limites históricos; `labels`/`valores` são mantidos para clientes antigos. Clientes com vários sensores devem usar `pontos` para desenhar séries separadas.

Em `relatorio.php`, `val_min`/`val_max` filtram valores e `pagina` controla o histórico de 200 linhas por página. `total`, `resumo`, `alertas` e a estimativa de períodos cobrem a consulta inteira. As duas datas delimitam dias completos no horário de Brasília. Intervalos ou valores invertidos e filtros inválidos são rejeitados.

`?formato=csv` ou `?formato=pdf`, combinados com os filtros, retornam um arquivo com **todos** os registros, sem o limite de paginação. PDF inclui identificação, período, resumo, gráficos, alertas, histórico e emissão. CSV inclui estoque, sensor, tipo, unidade, horário, valor e situação.

### Alertas

Filtros: `estoque_id`, `sensor_id`, `severidade=info/atencao/critico`, `status=pendente/reconhecido/normalizado/resolvido`, `data_inicial`, `data_final`, `pagina` (50 por página). O campo legado `resolvido` foi mantido; novas integrações devem usar também `status` e as datas do ciclo.

```json
{"acao":"reconhecer","alerta_id":1}
```

```json
{"acao":"resolver","alerta_id":1,"observacoes":"Vedação ajustada; leitura estabilizada."}
```

Reconhecer exige pendência; normalizar é automático ao receber valor normal; resolver registra responsável, horário e observações. Resolver antes da normalização conclui o atendimento, mas não altera o valor ambiental nem cria novos alertas repetidos durante a mesma irregularidade.

## Aplicativo Flutter

O código do aplicativo não foi anexado, portanto não foi modificado nem compilado. A API continua aceitando autenticação Bearer e os endpoints originais. Para usar os recursos novos, o cliente deve enviar `estoque_id` no cadastro de sensores, trabalhar com `estoque_ids` na gestão de usuários e exibir os novos filtros/estados. Clientes antigos que dependam de acesso global devem ser atualizados para respeitar o novo escopo.

## Revisão 3: conta e preferências

`GET /api/conta.php`, autenticado por Bearer, devolve `usuario` com `nome`, `login`, `tipo`, `status`, `criado_em`, `tema`, `foto` e `administrador_principal`, sem hash de senha.

`POST /api/conta.php` permite:

- `nome`, `tema` (`claro`, `escuro`, `automatico`).
- `senha_atual`, `senha`, `confirmar_senha`: troca de senha da própria conta.
- `remover_foto: "1"`: remove a imagem. Upload em multipart com campo `foto` aceita JPEG/PNG até 2 MB e 16 megapixels; é regravado como PNG de até 512 px. Em respostas, `foto` contém uma URL de dados PNG ou `null`.
- `acao: "tema"`, `tema`: salva somente a preferência visual.

A identidade vem da sessão. Enviar `id`, `usuario_id`, `tipo`, `status`, `estoque_ids` ou `administrador_principal` nessa rota é recusado (422). A API de gestão de usuários também recusa edição/desativação do administrador principal. Observações na resolução de alertas continuam opcionais.

Relatórios aceitam `por_pagina` (25, 50, 100 ou 200; padrão da API 200). Retornam `total` e `total_alertas` integrais. A lista resumida `alertas` contém até 25 entradas na consulta paginada; exportar PDF inclui todas. `horas` (6, 12, 24, 48 ou 168), sem datas, seleciona uma janela móvel coerente com o monitoramento. Datas explícitas têm prioridade. Datas JSON continuam em formato SQL para compatibilidade; HTML/CSV/PDF usam apresentação brasileira.

As rotas `routes/historico.php` e `routes/exportar.php` usam a sessão web, validam o acesso aos estoques e atendem aos botões da interface. O aplicativo externo continua usando os endpoints Bearer de `api/`.
