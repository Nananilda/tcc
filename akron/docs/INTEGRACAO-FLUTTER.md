# Integração com o aplicativo Flutter — pontos a verificar

O código Flutter **não foi fornecido**; esta revisão cobriu apenas o lado da API. Que os endpoints existam não significa que a integração funcione: cada item abaixo precisa ser testado no aplicativo real.

| Área | O que verificar no app | Lado API (estado) |
|---|---|---|
| Autenticação | `POST api/login.php`; guardar o token com segurança; reenviar em `Authorization: Bearer` | Funciona (testado por script). **Novo:** após 5 falhas em 15 min a API responde **HTTP 401** com `mensagem` informando o bloqueio e o tempo restante (não há código distinto de 401 por senha errada); o app deve exibir essa `mensagem`. |
| Sessão expirada | Tratar 401 e voltar ao login | API responde 401 com JSON |
| Permissões | Usuário comum vê só estoques autorizados | Aplicado no servidor (testado em `api_test.py`) |
| Estoques | Consumir os novos campos `condicao_ambiental`, `comunicacao_estado`, `situacao_cadastral`, `sensores_online`, `sensores_ativos` | `condicao` agora contém **somente** a condição ambiental (antes podia valer "Sem comunicação"). **Verificar se o app lê `condicao`.** |
| Sensores/valores atuais | Exibir `comunicacao`, `lido_em`, `ultima_origem` | Disponíveis |
| Gráficos | Respeitar `lacunas` e `janela` (não ligar pontos separados por lacuna) | Série traz `lacuna_antes`, `lacunas`, `sensores_desatualizados` |
| Alertas | Estados pendente/reconhecido/normalizado/resolvido | Disponível |
| Falhas de rede | Timeout, offline, mensagem clara, manter último dado com aviso | Responsabilidade do app |
| Consistência | Comparar mesmos números entre app e site no mesmo instante | Não testado |
