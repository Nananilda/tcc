<?php
/**
 * DashbordController.php
 * Reúne os números de resumo exibidos no painel principal
 * (app/views/dashboard/painel.php): sensores ativos, alertas pendentes
 * e total de leituras recentes.
 *
 * Segue o mesmo padrão dos outros controllers: tenta consultar o banco
 * real e, se as tabelas ainda não existirem/estiverem vazias, cai para
 * uma base de dados de exemplo (mesma lógica usada nos gráficos).
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
            'sensores_ativos'    => $this->contarSensoresAtivos(),
            'sensores_total'     => $this->contarSensoresTotal(),
            'alertas_pendentes'  => $this->contarAlertasPendentes(),
            'ultima_atualizacao' => date('d/m/Y H:i'),
        ];
    }

    private function contarSensoresTotal(): int
    {
        try {
            $model = new SensorModel($this->pdo);
            return count($model->listarTodos());
        } catch (PDOException $e) {
            // Base de exemplo enquanto a tabela `sensores` não está populada
            return 6;
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
            // Base de exemplo
            return 5;
        }
    }

    private function contarAlertasPendentes(): int
    {
        try {
            $model = new AlertaModel($this->pdo);
            $contagem = $model->contarPorSeveridade();
            $total = array_sum($contagem);
            return $total > 0 ? $total : array_sum($model->contarPorSeveridadeExemplo());
        } catch (PDOException $e) {
            $model = new AlertaModel($this->pdo);
            return array_sum($model->contarPorSeveridadeExemplo());
        }
    }
}
