# Alterações entregues

## Base preservada

O projeto continua em PHP/MySQL, com a organização em models/controllers/views, login com hash, dois níveis de acesso, sessões, CSRF e API Bearer. As seis grandezas do sistema original foram mantidas para preservar dados e integrações; temperatura, umidade e qualidade do ar têm prioridade na apresentação.

As solicitações do PDF exigiam mudanças de regras específicas. Foram implementadas dentro dessa base, sem trocar a tecnologia ou eliminar o histórico.

## Estrutura e funcionamento

- `estoques` é a entidade central; `usuario_estoque` registra as autorizações.
- Sensores pertencem a estoques, com quatro limites opcionais e prazo de comunicação individual.
- Leituras guardam estoque, classificação e limites usados no momento do registro. Mover um sensor não transfere o histórico para o estoque novo. O tipo não pode ser trocado se houver leituras, preservando suas unidades.
- A migração cria “Estoque inicial” para os sensores antigos, preserva cadastros e distribui acesso aos usuários existentes. Os limites iniciais são os mesmos que estavam no código original; não são recomendações técnicas para todos os materiais.
- Exclusões antigas de sensores/usuários agora desativam; estoques também são desativados. As chaves estrangeiras protegem leituras e alertas contra exclusão em cascata.
- Administradores gerenciam os cadastros e vinculam usuários; contas comuns consultam apenas estoques autorizados. Alterações de perfil, inativação e revogação de vínculo têm efeito nas sessões já abertas.

## Monitoramento e alertas

- Situação cadastral e comunicação são distintas. A comunicação depende da última leitura e do prazo configurado; estoque desativado interrompe o recebimento.
- O painel mostra estoques, sensores online, alertas ativos, estoques com irregularidades, valores atuais, gráficos ambientais e alertas recentes. A última atualização representa o horário da leitura recebida.
- Monitoramento oferece filtros por estoque, sensor, tipo e datas/janela. Painel e Monitoramento consultam os dados a cada 15 segundos, sem recarregar a página. Falhas ficam visíveis; valores antigos são identificados.
- Alertas automáticos são criados na mesma transação da leitura. O bloqueio da linha do sensor impede que envios simultâneos gerem ocorrências duplicadas.
- Reconhecimento, normalização e resolução têm datas e responsáveis próprios. A resolução aceita observações. Uma irregularidade contínua não gera alertas repetidos; o maior grau observado é preservado até a normalização.
- A transferência entre estoques exige normalização da ocorrência automática em andamento. Isso evita atribuir a outro local uma ocorrência ainda aberta.
- Valores iguais aos limites permanecem permitidos; campos de limite vazios desativam aquela regra. Os limites preenchidos devem seguir ordem crescente.

## Relatórios e interface

- Filtros coerentes entre consultas, gráficos e exportações; estatísticas calculadas sobre todo o período.
- Histórico paginado na tela; CSV e PDF exportam todos os registros filtrados. O PDF é gerado no servidor, com gráficos vetoriais, resumo, alertas, histórico completo e data de emissão, sem serviço externo.
- A estimativa de tempo fora dos limites usa somente pares consecutivos de leituras dentro do prazo de comunicação. Lacunas e períodos posteriores à última amostra não são preenchidos. Com filtro de valor, a estimativa fica desativada.
- Identidade ÁKRON preservada: cores centralizadas, componentes comuns, sidebar, títulos, botões, formulários agrupados, tabelas, estados vazios, badges com texto, temas claro/escuro, foco de teclado e menu móvel recolhível.
- Login mantém o formulário e inclui a seção “Conheça o ÁKRON” solicitada. Perfil exibe dados da própria conta e os estoques autorizados.

## Segurança, instalação e compatibilidade

- Consultas preparadas, permissões por estoque no backend/API, proteção CSRF nos formulários, hashes de senha e autenticação do gateway por chave.
- Ações administrativas persistidas em `auditoria`, incluindo vínculos, configuração de sensores, estoques e atendimento de alertas.
- Migração não destrutiva, retomável, com marcação de versão. Testada sobre o esquema do ZIP e sobre o esquema do TXT sem `usuario.criado_em`.
- API documentada, mantendo rotas e campos legados úteis, acrescentando recursos de estoques. Novos sensores precisam informar `estoque_id`; aplicativos externos devem incluir os campos novos.
- SQL de instalação não apaga mais o banco. Demonstração opcional não limpa registros existentes.

## Limites da entrega e cuidados com dados antigos

- O ZIP recebido não continha o aplicativo Flutter. O app externo não foi alterado nem compilado; esta entrega inclui o web e sua API adaptada.
- Leituras físicas foram simuladas pela API e pelo formulário. Calibração, conectividade e compatibilidade do hardware real precisam ser conferidas no local.
- Os limites históricos de leituras antigas são reconstruídos a partir das regras do código original. O banco anterior não guardava datas de reconhecimento/normalização/resolução; horários desconhecidos permanecem nulos, sem inventar dados.
- O sistema possui um segredo de gateway por instalação. Não inclui provisionamento individual de chaves por dispositivo, MQTT ou calibração física, que não faziam parte da base recebida.
- Gráficos interativos usam médias por intervalo para limitar o desenho; relatórios/exportações mantêm cada leitura e os mínimos/máximos reais.
- A instalação foi validada em PHP 8.3/MariaDB 11.4. O passo a passo do XAMPP está documentado, mas a máquina Windows do usuário não foi acessada.

## Correção da atualização de instalações existentes

- Detecta o banco legado antes das consultas do painel, evitando o erro `Unknown column s.estoque_id`.
- Inclui `atualizar-banco.php`: atualização pelo navegador com administrador autenticado, confirmação de backup e proteção CSRF.
- Compartilha a migração entre navegador e terminal, com bloqueio para impedir duas migrações simultâneas.
- Mantém cadastros e histórico; reexecuções não duplicam vínculos nem recalculam medições já migradas.
- A API retorna uma resposta JSON de manutenção enquanto o banco não está atualizado.
