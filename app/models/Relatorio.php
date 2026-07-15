<?php

class Relatorio
{
    private PDO $pdo;

    private array $sensor_tipos = [
        'temperatura',
        'ruido',
        'qualidade_ar',
        'umidade',
        'pressao',
        'uv',
    ];

    private array $labels_pt = [
        'temperatura'  => 'Temperatura (°C)',
        'ruido'        => 'Ruído (dB)',
        'qualidade_ar' => 'Qualidade do Ar (AQI)',
        'umidade'      => 'Umidade (%)',
        'pressao'      => 'Pressão (hPa)',
        'uv'           => 'UV (índice)',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Getters de metadados ─────────────────────────────────────────────────

    public function getSensorTipos(): array
    {
        return $this->sensor_tipos;
    }

    public function getLabels(): array
    {
        return $this->labels_pt;
    }

    public function getLabelPorTipo(string $tipo): string
    {
        return $this->labels_pt[$tipo] ?? $tipo;
    }

    // ── Montagem dos parâmetros e cláusula WHERE ─────────────────────────────

    /**
     * Recebe os filtros vindos do controller e devolve
     * ['where' => string, 'params' => array].
     */
    public function montarFiltros(
        string $data_ini,
        string $data_fim,
        string $sensor   = '',
        string $val_min  = '',
        string $val_max  = ''
    ): array {
        $params = [
            ':ini' => $data_ini . ' 00:00:00',
            ':fim' => $data_fim . ' 23:59:59',
        ];
        $where = "WHERE lido_em BETWEEN :ini AND :fim";

        if ($sensor && in_array($sensor, $this->sensor_tipos)) {
            $where .= " AND sensor_tipo = :tipo";
            $params[':tipo'] = $sensor;
        }
        if ($val_min !== '' && is_numeric($val_min)) {
            $where .= " AND valor >= :vmin";
            $params[':vmin'] = (float) $val_min;
        }
        if ($val_max !== '' && is_numeric($val_max)) {
            $where .= " AND valor <= :vmax";
            $params[':vmax'] = (float) $val_max;
        }

        return ['where' => $where, 'params' => $params];
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    /**
     * Retorna até 200 leituras detalhadas (mais recentes primeiro).
     */
    public function buscarLeituras(string $where, array $params): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sensor_tipo, valor, lido_em
            FROM leitura_sensor
            $where
            ORDER BY lido_em DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna resumo estatístico agrupado por tipo de sensor.
     */
    public function buscarResumo(string $where, array $params): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sensor_tipo,
                   COUNT(*)             AS total,
                   ROUND(AVG(valor), 2) AS media,
                   ROUND(MIN(valor), 2) AS minimo,
                   ROUND(MAX(valor), 2) AS maximo
            FROM leitura_sensor
            $where
            GROUP BY sensor_tipo
            ORDER BY sensor_tipo
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
