<?php
/**
 * app/controllers/LeituraController.php
 * Controller: LeituraController
 * Recebe uma nova leitura de sensor, grava no banco e, se o valor ultrapassou
 * algum limite (AlertaModel::LIMITES), cria o ALERTA AUTOMÁTICO correspondente.
 *
 * É a "porta de entrada" dos dados dos sensores e tem dois usos:
 *   - api/leituras.php (POST): sensores/gateways (com a chave X-Chave-Sensor)
 *     ou um administrador logado enviam {sensor_id, valor};
 *   - tela web "Novo alerta" (app/views/graficos/novo_alerta.php): o administrador
 *     simula uma leitura para testar os limites durante a apresentação.
 *
 * A consulta das leituras para os gráficos fica no GraficoController /
 * LeituraModel::serie().
 */

require_once __DIR__ . '/../models/LeituraModel.php';
require_once __DIR__ . '/../models/SensorModel.php';
require_once __DIR__ . '/../models/AlertaModel.php';

class LeituraController
{
    private LeituraModel $leituras;
    private SensorModel $sensores;
    private AlertaModel $alertas;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->leituras = new LeituraModel($pdo);
        $this->sensores = new SensorModel($pdo);
        $this->alertas = new AlertaModel($pdo);
    }

    /**
     * Registra uma leitura e gera o alerta automático, se for o caso.
     *
     * @param array    $dados     sensor_id e valor (aceita vírgula decimal)
     * @param int|null $usuarioId quem enviou (null = sensor/gateway), só para o log
     * @return array{mensagem:string,erros:string[],leitura_id?:int,alerta?:?array}
     *         `alerta` é null quando o valor está dentro dos limites; senão
     *         {id, severidade, mensagem, novo} — `novo` é false quando já havia
     *         um alerta pendente igual (nenhum duplicado é criado).
     */
    public function registrar(array $dados, ?int $usuarioId = null): array
    {
        $sensorId = (int) ($dados['sensor_id'] ?? 0);
        $valor = lerNumero($dados['valor'] ?? null);

        $erros = [];
        if ($sensorId <= 0) {
            $erros[] = 'Selecione o sensor da leitura.';
        }
        if ($valor === null) {
            $erros[] = 'Informe o valor da leitura como um número (ex.: 36,5).';
        }
        if (!empty($erros)) {
            return ['mensagem' => '', 'erros' => $erros];
        }

        try {
            $sensor = $this->sensores->buscarPorId($sensorId);
            if (!$sensor) {
                return ['mensagem' => '', 'erros' => ['Sensor não encontrado.']];
            }
            if ($sensor['status'] !== 'ativo') {
                return ['mensagem' => '', 'erros' => ["O sensor \"{$sensor['nome']}\" está inativo e não pode enviar leituras."]];
            }

            // Leitura e alerta andam juntos: ou gravam os dois, ou nenhum
            $this->pdo->beginTransaction();
            $leituraId = $this->leituras->registrar($sensorId, $valor);
            $alerta = $this->gerarAlertaAutomatico($sensor, $valor);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[' . APP_NOME . '] Erro ao registrar leitura: ' . $e->getMessage());
            return ['mensagem' => '', 'erros' => ['Não foi possível registrar a leitura. Tente novamente.']];
        }

        $this->registrarLog($usuarioId, 'LEITURA_REGISTRADA', "Sensor ID $sensorId: $valor");

        $mensagem = 'Leitura registrada.';
        if ($alerta !== null) {
            $grau = ['critico' => 'crítico', 'atencao' => 'de atenção', 'info' => 'informativo'][$alerta['severidade']];
            $mensagem .= $alerta['novo']
                ? " Alerta $grau gerado automaticamente: {$alerta['mensagem']}."
                : " Já existe um alerta $grau pendente para este limite: {$alerta['mensagem']}.";
        }

        return ['mensagem' => $mensagem, 'erros' => [], 'leitura_id' => $leituraId, 'alerta' => $alerta];
    }

    /**
     * Aplica os limites do tipo do sensor e cria o alerta, evitando duplicar
     * um alerta igual que ainda esteja pendente.
     *
     * @return array{id:int,severidade:string,mensagem:string,novo:bool}|null
     */
    private function gerarAlertaAutomatico(array $sensor, float $valor): ?array
    {
        $violacao = AlertaModel::avaliarLimite($sensor['tipo'], $valor);
        if ($violacao === null) {
            return null;
        }

        $sensorId = (int) $sensor['id'];
        $existente = $this->alertas->buscarPendenteIgual($sensorId, $violacao['severidade'], $violacao['mensagem']);
        if ($existente !== null) {
            return $violacao + ['id' => $existente, 'novo' => false];
        }

        $id = $this->alertas->criar($sensorId, $violacao['severidade'], $violacao['mensagem'], $valor);
        $this->registrarLog(null, 'ALERTA_AUTOMATICO', "Alerta ID $id ({$violacao['severidade']}): sensor $sensorId, valor $valor");
        return $violacao + ['id' => $id, 'novo' => true];
    }

    private function registrarLog(?int $usuarioId, string $acao, string $detalhe): void
    {
        if (function_exists('registrarLog')) {
            registrarLog($this->pdo, $usuarioId, $acao, $detalhe);
        }
    }
}
