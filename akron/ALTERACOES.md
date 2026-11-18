# Alterações — revisão 3.0

Referência: `alterações tcc (1).docx`, fornecido junto com `tcc.zip`. A marca, as cores, fontes locais, PHP, MySQL e rotas existentes foram preservados. A revisão anterior foi mantida em `docs/HISTORICO-V2.md`.

| Grupo do documento | Implementação |
| --- | --- |
| 1. Padronização | Componentes compartilhados, alinhamento à esquerda, espaçamentos e estados com texto; datas brasileiras e hora de 24 h; temas claro, escuro e automático salvos por usuário. |
| 2. Login | Explicação retirada do corpo da página e acessível pelo botão `?`, em janela com fechamento por botão ou Escape. |
| 3. Minha conta | Navegação própria; edição do nome, senha mediante senha atual, foto JPG/PNG (2 MB), substituição/remoção e avatar padrão. Nenhuma permissão administrativa pode ser alterada por essa área. |
| 4. Painel | Grade flexível de sensores, valores/unidades/estados, última leitura real e alertas relevantes. Os gráficos mantêm os horários nos dados e exibem somente datas no eixo. |
| 5. Estoques | Cartões em três, duas ou uma coluna, resumo ambiental, comunicação, alertas, situação, detalhes e edição. Estado sem sensores identificado. |
| 6. Detalhes do estoque | Estado geral em destaque; leituras atuais; gráficos por variável; filtros de período e sensor; cinco alertas iniciais, mais dez por clique; leituras em grupos de 25; CSV e PDF com identificação, sensores, leituras, alertas e resumo. |
| 7. Sensores | Cadastro junto ao filtro; situação cadastral separada da comunicação; identificação, comunicação e limites em grupos; histórico progressivo e exportação completa. |
| 8. Monitoramento | Temperatura, umidade, qualidade do ar, ruído, pressão e UV. Gráficos separados, limites, filtros combinados, data real da leitura e diferenciação entre leitura atual, média, sem leitura e desatualização. |
| 9. Alertas | Cartões expansíveis; gravidade, mensagem, estoque/sensor, valor, limite, horário e situação. Observações opcionais; reconhecimento, normalização e resolução separados. Filtros combinados e CSV/PDF sem truncamento pela página. |
| 10. Relatórios | Grade por variável, unidades e limites; estatísticas integrais; páginas de 25/50/100/200 leituras; todos os dados filtrados no CSV/PDF. Janelas de horas também respeitadas ao navegar do monitoramento. |
| 11. Usuários | Foto/avatar, nome, perfil, situação e estoques. Estoques selecionáveis por cartões no cadastro e edição. Administrador principal protegido também no servidor/API. |
| 12. Desativação | Primeiro clique exibe consequências e botão vermelho; segundo confirma. Cancelar restaura o botão. Desativar preserva histórico; as rotas antigas chamadas `excluir` continuam desativando, sem apagar dados. A exclusão definitiva passou a existir na revisão 3.2 (veja abaixo), somente para registros sem histórico. |
| 13. Celular | Navegação recolhível, formulários em uma coluna, cartões de estoque em uma coluna, gráficos adaptáveis e linhas de tabelas convertidas em cartões com rótulos. |
| 14. Formulários | Campos obrigatórios, mensagens de validação, preservação do preenchimento, agrupamentos, indicadores de processamento e confirmação ao sair com alterações não salvas. |
| 15. Histórico e confiabilidade | Carregamento progressivo/paginação; filtros mantidos; médias identificadas; nenhum simulador automático; dados desatualizados sinalizados; consultas com escopo e resumo consistente. |
| 16. Organização | Componentes de alertas, leituras, seleção de estoques, histórico e exportação compartilhados. Controlador de conta comum a web e API. Sem bibliotecas novas para executar o site, além da extensão GD para fotos. |
| 17. Validação | Integração com banco real de teste, migrações, permissões, conta/foto, alertas, exportações e navegador em três tamanhos e dois temas. Evidências em `docs/VALIDACAO.md`. |

## Comportamentos importantes

- Tema fica no banco; o botão no cabeçalho alterna automático → claro → escuro. O visitante tem uma preferência separada no navegador.
- Fotos são decodificadas e regravadas como PNG de até 512 pixels; arquivos executáveis/SVG não são aceitos. A configuração não exige permissão de escrita na pasta do site.
- A migração preserva contas, vínculos de acesso, limites desativados, leituras e alertas da versão 2. Executá-la novamente não duplica registros.
- Em gráficos, pontos agregados identificam a média e o intervalo no tooltip. Os horários completos e cada leitura continuam disponíveis no histórico e nas exportações. A resolução do gráfico acompanha a extensão dos dados existentes.
- O tempo fora dos limites é uma estimativa entre amostras consecutivas dentro do prazo do sensor. Não são inventados valores durante lacunas de comunicação.
- CSV de relatórios contém todas as leituras filtradas. CSV de alertas contém todas as ocorrências filtradas. CSV dos detalhes é dividido em seções (identificação, sensores, resumo, leituras e alertas); PDF dos detalhes inclui essas mesmas informações.
- A página de relatórios mostra até 25 alertas no resumo para não crescer indefinidamente. A página Alertas permite percorrer todos; o PDF do relatório inclui o conjunto completo.
- Código Dart/Flutter e dispositivos físicos não estavam no ZIP. A API de sessão por Bearer foi mantida; foi acrescentado `api/conta.php`. A validação de hardware e de aplicativos externos depende desses componentes.


## Revisão 3.1

**Segurança.** Bloqueio real de tentativas de login (tabela `tentativas_login`; 5 falhas/15 min por IP e por login; liberação automática). Proteção HTTP de `sql/`, `tests/`, `docs/`, `includes/`, `app/config/`, `app/models/`, `app/controllers/`, `app/services/` e `app/views/partials/` por `.htaccess`; `router-dev.php` para `php -S`; regras nginx em `docs/SEGURANCA-SERVIDOR.md`.

**Estado dos estoques.** `EstoqueModel::classificar()` separa condição ambiental, comunicação e situação cadastral. Falha de comunicação não vira irregularidade ambiental; só leituras recentes de sensores ativos entram na avaliação; estoque sem sensores é "Indisponível".

**Gráficos.** Eixo temporal real, sem `spanGaps`; lacunas detectadas no servidor e desenhadas como faixa hachurada; eixo só com datas, horário exato no tooltip; leitura individual × média diferenciadas. Monitoramento com gráficos recolhíveis, seleção de tipos e preferências salvas. Painel só exibe tipos com dados.

**Interface.** Cartões de estoque, estado do estoque, alertas (10/25/50/100, faixa de críticos pendentes, estados distintos), Minha conta (foto com prévia/cancelar, senha expansível, estoques compactos), seções do sensor, filtros de sensores, textos "Selecione…" no lugar de "—".

**Dados.** `leitura_sensor.origem`; `estoques.demonstracao` e `usuario.demonstracao`; `sql/demonstracao.php` (substitui `sql/dados_exemplo.sql`).

**Migração.** Versão `2026_revisao_31`; execute `php sql/migrar.php` ou abra `atualizar-banco.php` como administrador. Faça backup antes. Instalações já migradas não têm vínculos nem limites regravados.

**Documentos novos.** `docs/LIMITES-AMBIENTAIS.md`, `docs/SEGURANCA-SERVIDOR.md`, `docs/SENSORES-E-INTEGRACAO.md`, `docs/INTEGRACAO-FLUTTER.md`.


## Revisão 4.0 — interface e experiência

Somente apresentação; banco, APIs, rotas, autenticação e permissões não foram alterados.

- **Painel** (`dashboard/painel.php`, `painel.css`, `painel.js`): central de módulos em três grupos (Monitoramento, Gestão, Pessoal), indicadores reais por importância, sem menu lateral. Registro único dos módulos em `partials/modulos.php`.
- **Cabeçalho** (`partials/cabecalho.php`): marca, trilha Painel / Grupo / Página, conta e Sair em todas as páginas internas.
- **Configurações pessoais** (`usuarios/perfil.php`, `conta.js`, `tema.js`): Perfil, Aparência (cartões claro/escuro/automático, salvos na hora), Segurança e Acessos. A alternância de tema saiu do login e do cabeçalho.
- **Login** (`index.php`, `login.css`): símbolo centralizado sob o cartão, informações do sistema no canto inferior direito, "?" no canto superior direito. `login.css` agora carrega por último (`$cssPaginaFinal`), eliminando os conflitos com `componentes.css` e `revisao.css`.
- **Sensores**: `listar_sensores.php` tem modo consulta e modo gestão (`?modo=gestao`, somente administrador).
- **CSS**: removidas regras do menu lateral, do botão de tema e do menu antigo de atalhos; tokens de acento por módulo, elevação e movimento em `style.css`; animações respeitam `prefers-reduced-motion`.
- Gráficos e leituras atuais continuam em Monitoramento; o painel mantém os alertas recentes.


## Revisão 3.2 — navegação, padronização e exclusão

Sem mudanças no banco, na autenticação, na integração ESP32, nas APIs nem nas regras de monitoramento. Nenhum arquivo foi criado, movido ou renomeado.

**Navegação (Voltar/Cancelar).** Cada link que sai de uma página leva o parâmetro `voltar` com a página de origem (filtros, página da listagem, ID, estoque e modo de gestão incluídos). Voltar, Cancelar e o destino após salvar usam esse endereço; no acesso direto cada página tem um destino padrão válido (edição → detalhes; cadastro → listagem; monitoramento/relatório → sensor ou estoque filtrado, ou painel). O valor só é aceito se for uma página interna da lista fechada `NAV_DESTINO_VALIDO` (`app/config/app.php`), nunca um endereço externo. Helpers: `urlComOrigem()`, `urlVoltar()`, `linkVoltar()`, `linkCancelar()`, `campoVoltar()`, `parametrosNavegacao()`.

**Envio de formulários.** Cadastro, edição, desativação, exclusão, criação/atendimento de alertas e configurações pessoais usam Post/Redirect/Get (`redirecionarComMensagem()`): atualizar a página não reenvia o formulário nem duplica registros. Em caso de erro a página permanece com os dados digitados.

**Mensagens.** `mensagensPagina()` padroniza sucesso (`msg-sucesso`) e erros (`msg-erro` com lista) em todas as páginas.

**Visual.** Cabeçalho único (`page-header` com título, subtítulo e ações), botões com o mesmo tamanho/alinhamento, cartões, grades de formulário e ações com os mesmos espaçamentos; regras no fim de `revisao.css`. Links duplicados de retorno (`nav-rodape`) removidos. Ajustes de ações empilhadas para celular e tablet.

**Excluir.** Além de Desativar, existe Excluir em sensores, estoques e usuários (somente administrador, com confirmação em dois cliques e somente nas telas web — a API não oferece exclusão definitiva), e exclusão automática de alertas:
- sensores, estoques e usuários só podem ser excluídos **sem histórico** (leituras/alertas/sensores vinculados; no caso de usuários, também acessos registrados e alertas atendidos). Com histórico, o sistema bloqueia e orienta a usar Desativar. Também continuam protegidos o administrador principal, a própria conta e o último administrador ativo;
- alertas **resolvidos** são excluídos **automaticamente 30 dias após a resolução**. Alertas pendentes ou reconhecidos nunca são excluídos. Como a hospedagem não deve ser alterada (sem cron), a limpeza roda quando alguém abre o painel ou a página de Alertas, no máximo uma vez por hora por sessão (`AlertaModel::limpezaAutomatica()`). A página de Alertas avisa todos os usuários quando há alertas que serão excluídos nos próximos 7 dias (sugerindo exportar CSV/PDF antes) e cada cartão mostra “será excluído automaticamente em N dia(s)”. Não há botão de exclusão manual de alertas.
- Registro em auditoria: `SENSOR_EXCLUSAO`, `ESTOQUE_EXCLUSAO`, `EXCLUSAO_USUARIO`, `ALERTA_EXCLUSAO_AUTOMATICA` (sem usuário, com a quantidade).
