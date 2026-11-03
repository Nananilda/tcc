# Validação da revisão 3.0

Executada em 02/11/2026 em ambiente isolado com PHP 8.3, PDO MySQL, GD, mbstring, MariaDB 11.4 e Chromium automatizado com Playwright. O projeto executa sem Node/Composer; Playwright é utilizado apenas para testes de navegador.

## Resultados

| Verificação | Resultado |
| --- | --- |
| `tests/api_test.py` | 74 verificações passaram: autenticação, escopo por estoque, limites, ingestão, geração/deduplicação/normalização/reconhecimento/resolução de alertas, revogação, exportação integral e preservação do histórico em transferências/desativações. |
| `tests/revisao_test.py` | 42 verificações passaram: conta própria, proteção do administrador, senha atual/nova, tema individual/automático, envio/substituição/remoção de foto, recusa de arquivo inválido, seis tipos, histórico progressivo e CSV/PDF. |
| `tests/ui_test.cjs` | 182 verificações passaram: páginas e interações, sem erros JavaScript e sem ultrapassar a largura da tela. Desktop 1440×1000, tablet 820×1180 e celular 390×844, com temas claro e escuro. Inclui 5+10 alertas, tooltip com horário, seleção por cartões, tema persistente e confirmação/cancelamento/reativação. |
| `tests/ui_realtime.cjs` | 6 verificações passaram: nova leitura aparece no ciclo automático de 15 s, gráfico atualizado, falha de conexão sinalizada, manutenção dos dados anteriores, recuperação e confirmação de saída com alterações não salvas. |
| `tests/migration_web_test.py` | 18 verificações passaram: acesso à migração, proteção administrativa, CSRF, confirmação de backup, telas após atualização, repetição segura e acesso de usuário legado. |
| Migração de uma base v2 já atualizada | 6 verificações passaram: conteúdo anterior de usuários, vínculos de acesso, sensores, leituras e alertas preservado; segunda execução sem alterações. Incluiu permissões removidas e limites ambientais desativados antes da migração. |
| PDFs e filtros | 5 verificações passaram: três PDFs abertos e extraídos com `pypdf`, contendo identificação/limites/auditoria; janela de 6 h consistente entre gráfico e relatório; página de 25 com estatísticas de todo o conjunto. |
| Sintaxe | Todos os 75 arquivos PHP validados com `php -l`; quatro scripts JavaScript próprios validados com `node --check`. |

As capturas de `docs/capturas-v3` foram produzidas nessa execução com registros identificados pelo prefixo de teste. O banco de teste e suas credenciais não fazem parte do pacote. As pastas de capturas anteriores continuam como referência histórica.

## Reproduzir testes

Use exclusivamente um banco descartável: as suítes criam usuários, estoques, sensores, leituras e alertas.

```sh
python3 tests/api_test.py http://127.0.0.1:8000/api
AKRON_TEST_OUTPUT=/tmp/akron-revisao python3 tests/revisao_test.py http://127.0.0.1:8000
```

`AKRON_TEST_LOGIN` e `AKRON_TEST_PASS` configuram o administrador das suítes Python. A suíte de migração deve apontar para uma instalação de teste ainda não migrada, como explicado no próprio arquivo.

Para as suítes de navegador, instale Playwright em uma pasta de ferramentas, inicie um Chromium local com depuração remota e configure `AKRON_CDP`, `AKRON_TEST_URL` e `AKRON_TEST_CONTEXT` (o arquivo `context.json` gerado por `revisao_test.py`). Os testes de interface usam `admin` / `Admin@123` na base descartável. Se necessário, ajuste essa credencial no teste. As capturas usam `/app/docs/capturas-v3` no contêiner da validação; ajuste o caminho para executar fora dele.

## Limites da validação

- XAMPP/Windows não foi executado neste ambiente Linux. O código permanece PHP/MySQL, com instruções específicas para XAMPP em `INSTALACAO.md`.
- Código Dart/Flutter não estava no ZIP. Não foi possível testar ou compilar um aplicativo móvel externo.
- Leituras foram enviadas pela API em um banco de teste. Não havia sensores físicos conectados.
- As estatísticas de tempo fora dos limites continuam estimativas apenas onde existem amostras consecutivas suficientes.
