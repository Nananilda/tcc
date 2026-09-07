<?php
/**
 * DashbordController.php
 * Reúne os números de resumo exibidos no painel principal
 * (app/views/dashboard/painel.php): sensores ativos, alertas pendentes
 * e total de leituras recentes — sempre a partir dos dados reais do banco.
 */

require_once __DIR__ . '/../models/Sensor.php';
require_once __DIR__ . '/../models/Alerta.php';

class DashbordController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna um array associativo pronto para exibição:
     * ['sensores_ativos', 'sensores_total', 'alertas_pendentes', 'ultima_atualizacao']
     */
    public function obterResumo(): array
    {
        return [
            'sensores_ativos' => $this->contarSensoresAtivos(),
            'sensores_total' => $this->contarSensoresTotal(),
            'alertas_pendentes' => $this->contarAlertasPendentes(),
            'ultima_atualizacao' => date('d/m/Y H:i'),
        ];
    }

    private function contarSensoresTotal(): int
    {
        try {
            $model = new SensorModel($this->pdo);
            return count($model->listarTodos());
        } catch (PDOException $e) {
            return 0;
        }
    }

    private function contarSensoresAtivos(): int
    {
        try {
            $model = new SensorModel($this->pdo);
            $sensores = $model->listarTodos();
            $ativos = array_filter($sensores, fn($s) => $s['status'] === 'ativo');
            return count($ativos);
        } catch (PDOException $e) {
            return 0;
        }
    }

    private function contarAlertasPendentes(): int
    {
        try {
            $model = new AlertaModel($this->pdo);
            $contagem = $model->contarPorSeveridade();
            return array_sum($contagem);
        } catch (PDOException $e) {
            return 0;
        }
    }
}
