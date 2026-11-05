# Tipos de sensor: suporte de software × integração física

Esta página separa o que o **software sabe cadastrar** do que foi **ligado a hardware real e testado**.

| Tipo | Cadastro e classificação por limites | Gráficos, relatórios, alertas | Integração física implementada | Validado com hardware |
|---|---|---|---|---|
| Temperatura (°C) | Sim | Sim | Não existe no repositório | Não |
| Umidade (%) | Sim | Sim | Não existe no repositório | Não |
| Qualidade do ar (AQI) | Sim | Sim | Não existe no repositório | Não |
| Ruído (dB) | Sim | Sim | Não existe no repositório | Não |
| Pressão (hPa) | Sim | Sim | Não existe no repositório | Não |
| Índice UV | Sim | Sim | Não existe no repositório | Não |

## O que existe de fato para entrada de dados

- `POST api/leituras.php` com o cabeçalho `X-Chave-Sensor` (gateway) ou sessão de administrador (registro manual).
- Não há firmware, driver, biblioteca de sensor, calibração, MQTT nem provisionamento por dispositivo neste repositório.
- A documentação anterior (`docs/HISTORICO-V2.md`) registra que as leituras foram simuladas pela API e pelo formulário.

**Conclusão a usar no TCC:** o sistema suporta o cadastro e o tratamento dos seis tipos; a integração com sensores físicos específicos **não foi implementada nem validada neste repositório**. Se houver outro repositório com o firmware, ele deve ser citado separadamente com seus próprios testes.

## Origem do dado (campo `leitura_sensor.origem`)

| Valor | Significado | Como é definido |
|---|---|---|
| `sensor` | Recebido pelo canal de gateway com chave | Automático, pela autenticação da requisição |
| `manual` | Registrado por administrador (tela ou API) | Automático |
| `demonstracao` | Gerado por `sql/demonstracao.php` | Automático |
| `nao_informada` | Leituras anteriores à revisão 3.1 | Migração (nunca presumido como sensor físico) |

Limitação: `sensor` indica **o canal**, não prova que um sensor físico gerou o valor — um gateway configurado para enviar valores simulados também aparece como `sensor`. A origem não é aceita no corpo da requisição, para não ser forjada por quem envia.
