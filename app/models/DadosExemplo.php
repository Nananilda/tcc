<?php
/**
 * DadosExemplo.php
 * Funções auxiliares que geram dados de exemplo (base) para os gráficos
 * de sensores funcionarem enquanto a tabela `leitura_sensor` do banco
 * real ainda não está criada/populada.
 *
 * Usado por:
 *   - app/views/graficos/graficos_sensores.php
 *   - app/controllers/GraficoController.php (endpoint AJAX)
 */

if (!function_exists('gerarLeiturasExemplo')) {

    /**
     * Gera leituras de exemplo para um sensor/período, com um formato
     * idêntico ao retornado pela consulta real (valor, lido_em).
     */
    function gerarLeiturasExemplo(string $sensor, int $horas): array
    {
        $faixas = [
            'temperatura'  => [18, 32],
            'ruido'        => [40, 95],
            'qualidade_ar' => [20, 180],
            'umidade'      => [30, 80],
            'pressao'      => [995, 1025],
            'uv'           => [0, 11],
        ];
        [$min, $max] = $faixas[$sensor] ?? [0, 100];

        $pontos = min(48, max(6, (int) ($horas / 2)));
        $intervaloSeg = (int) (($horas * 3600) / $pontos);

        $leituras = [];
        $agora = time();
        $valorAtual = ($min + $max) / 2;

        for ($i = $pontos; $i >= 0; $i--) {
            // Pequena variação aleatória em torno do valor anterior, mantida dentro da faixa
            $variacao = ($max - $min) * 0.05;
            $valorAtual += mt_rand((int) (-$variacao * 100), (int) ($variacao * 100)) / 100;
            $valorAtual = max($min, min($max, $valorAtual));

            $leituras[] = [
                'valor'   => round($valorAtual, 2),
                'lido_em' => date('Y-m-d H:i:s', $agora - ($i * $intervaloSeg)),
            ];
        }

        return $leituras;
    }
}
