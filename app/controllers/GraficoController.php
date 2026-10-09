<?php
/**
 * app/controllers/GraficoController.php
 * Controller: GraficoController
 * Entrega a série de leituras usada pelos gráficos de linha: tanto no
 * carregamento da página (app/views/graficos/graficos_sensores.php) quanto na
 * atualização em tempo real (AJAX, rota routes/grafico_dados.php) e na API
 * (api/leituras.php).
 */

require_once __DIR__ . '/../models/LeituraModel.php';

class GraficoController
{
    private LeituraModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new LeituraModel($pdo);
    }

    /**
     * Consulta a série de um sensor, validando os parâmetros recebidos.
     *
     * @param array $entrada normalmente $_GET: 'sensor' (tipo) e 'horas'
     * @return array{sensor:string, horas:int, pontos:array, total:int, erro:string}
     *         `pontos` → lista de ['lido_em', 'valor', 'amostras'];
     *         `total`  → soma das leituras brutas que compõem os pontos.
     */
    public function serie(array $entrada): array
    {
        $sensor = LeituraModel::normalizarSensor($entrada['sensor'] ?? null);
        $horas = LeituraModel::normalizarHoras($entrada['horas'] ?? 24);
        $pontos = [];
        $erro = '';

        try {
            $pontos = $this->model->serie($sensor, $horas);
        } catch (PDOException $e) {
            error_log('[' . APP_NOME . '] Erro ao consultar leituras: ' . $e->getMessage());
            $erro = 'Não foi possível consultar as leituras no banco de dados.';
        }

        return [
            'sensor' => $sensor,
            'horas' => $horas,
            'pontos' => $pontos,
            'total' => (int) array_sum(array_column($pontos, 'amostras')),
            'erro' => $erro,
        ];
    }

    /**
     * Mesma consulta, no formato JSON usado pelo JavaScript e pela API:
     * { sensor, horas, labels: [...datas], valores: [...números], total }
     */
    public function serieJson(array $entrada): array
    {
        $r = $this->serie($entrada);
        return [
            'sensor' => $r['sensor'],
            'horas' => $r['horas'],
            'labels' => array_column($r['pontos'], 'lido_em'),
            'valores' => array_column($r['pontos'], 'valor'),
            'total' => $r['total'],
        ];
    }
}
