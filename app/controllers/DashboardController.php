<?php
/**
 * app/controllers/DashboardController.php
 * Reúne os números de resumo exibidos no painel principal
 * (app/views/dashboard/painel.php) e devolvidos por api/dashboard.php:
 * sensores ativos, alertas pendentes e horário da última atualização —
 * sempre a partir dos dados reais do banco.
 */

require_once __DIR__ . '/../models/SensorModel.php';
require_once __DIR__ . '/../models/AlertaModel.php';

class DashboardController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna um array associativo pronto para exibição:
     * ['sensores_ativos', 'sensores_total', 'alertas_pendentes', 'ultima_atualizacao']
     *
     * Se o banco falhar, os números voltam zerados (e o erro vai para o log),
     * para o painel continuar abrindo.
     */
    public function obterResumo(): array
    {
        $sensoresAtivos = 0;
        $sensoresTotal = 0;
        $alertasPendentes = 0;

        try {
            $sensores = (new SensorModel($this->pdo))->contarPorStatus();
            $sensoresAtivos = $sensores['ativos'];
            $sensoresTotal = $sensores['total'];

            $alertasPendentes = array_sum((new AlertaModel($this->pdo))->contarPorSeveridade());
        } catch (PDOException $e) {
            error_log('[' . APP_NOME . '] Erro ao montar o resumo do painel: ' . $e->getMessage());
        }

        return [
            'sensores_ativos' => $sensoresAtivos,
            'sensores_total' => $sensoresTotal,
            'alertas_pendentes' => $alertasPendentes,
            'ultima_atualizacao' => date('d/m/Y H:i'),
        ];
    }
}
