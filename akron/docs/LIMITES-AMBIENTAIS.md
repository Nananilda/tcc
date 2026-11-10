# Limites ambientais — o que o sistema faz e o que ainda precisa de validação

## Três coisas diferentes

| Conceito | Onde vive | Quem define |
|---|---|---|
| **Limites configurados** | `sensores.limite_atencao_min/max` e `limite_critico_min/max` | O administrador, por sensor. São os **únicos** usados para classificar leituras e gerar alertas. |
| **Valores de referência** | Esta página (tabela abaixo) | Ninguém: são pontos de partida genéricos, **sem fonte técnica verificada**. |
| **Limites de um contexto de armazenamento** | Bula, especificação do fabricante, norma aplicável, política do estoque | O responsável técnico pelo produto/ambiente. |

## Comportamento atual do código

- Sensores **novos nascem sem limites**. Nada é preenchido automaticamente (o formulário avisa isso).
- Campo vazio = aquele limite está desativado.
- Cada leitura guarda uma cópia dos limites vigentes no momento do registro; alterar limites depois **não reescreve o histórico**.
- Cada alerta informa o limite ultrapassado.
- `AlertaModel::LIMITES` e a migração antiga (`MigracaoBanco`, usada uma única vez, só em sensores com todos os limites vazios) contêm os valores genéricos abaixo. A API (`alertas.php`) os expõe com a ressalva de que não são recomendações.

## Valores genéricos existentes no código — PRECISAM DE VALIDAÇÃO DOCUMENTAL

| Tipo | Atenção | Crítico | Justificativa técnica registrada no projeto |
|---|---|---|---|
| Temperatura (°C) | máx. 30 | máx. 35 | Nenhuma. Depende totalmente do produto (refrigerados, medicamentos, eletrônicos e grãos têm faixas muito diferentes). |
| Umidade (%) | 35 a 80 | 20 a 90 | Nenhuma. |
| Qualidade do ar (AQI) | máx. 100 | máx. 150 | Parecem inspirados em faixas de índices de qualidade do ar, mas o projeto não registra a fonte nem qual índice/escala o sensor usa. **Verificar.** |
| Ruído (dB) | máx. 85 | máx. 95 | Parecem inspirados em limites de exposição ocupacional, mas a fonte, o tempo de exposição e a norma aplicável não estão registrados. **Verificar** na norma vigente. |
| Pressão (hPa) | 1000 a 1030 | 990 a 1040 | Nenhuma. Pressão atmosférica varia com altitude e clima; o limite não faz sentido sem o contexto. |
| Índice UV | máx. 6 | máx. 8 | Nenhuma registrada. **Verificar.** |

> Os dados de demonstração (`sql/demonstracao.php`) usam limites próprios, ilustrativos, marcados no texto dos estoques de demonstração. Também não são recomendações.

## O que fazer antes de usar em um estoque real

1. Identificar o produto e a especificação do fabricante/norma aplicável.
2. Cadastrar os limites **por sensor** (tela *Editar sensor*).
3. Registrar a fonte na descrição do estoque ou em documento próprio do TCC.
4. Para o texto do TCC, citar somente referências efetivamente consultadas. Este projeto **não** fornece citações.
