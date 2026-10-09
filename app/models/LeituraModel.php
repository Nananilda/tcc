<?php
/**
 * app/models/LeituraModel.php
 * Model: LeituraModel
 * Consulta a tabela `leitura_sensor` para alimentar os gráficos de linha
 * (tela "Gráficos de Sensores" e o endpoint de atualização em tempo real).
 *
 * Cada leitura pertence a um sensor (`sensor_id`); o tipo (temperatura,
 * ruído...) vem do JOIN com `sensores`. Os relatórios têm um model próprio
 * (RelatorioModel).
 */

require_once __DIR__ . '/SensorModel.php'; // catálogo de tipos de sensor

class LeituraModel
{
    /** Janelas de tempo (em horas) oferecidas na tela de gráficos. */
    public const HORAS_VALIDAS = [6, 12, 24, 48, 168];

    /** Rótulos das janelas, na mesma ordem de HORAS_VALIDAS. */
    public const ROTULOS_PERIODO = [6 => '6 h', 12 => '12 h', 24 => '24 h', 48 => '48 h', 168 => '7 dias'];

    /** Quantidade aproximada de pontos desejada no gráfico. */
    private const PONTOS_ALVO = 120;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Texto da janela para títulos: 24 → 'últimas 24 h'; 168 → 'últimos 7 dias'. */
    public static function rotuloJanela(int $horas): string
    {
        return $horas >= 24 && $horas % 24 === 0 && $horas > 48
            ? 'últimos ' . ($horas / 24) . ' dias'
            : 'últimas ' . $horas . ' h';
    }

    // ── Normalização dos parâmetros recebidos (GET) ──────────────────────────

    /** Garante um tipo de sensor válido (padrão: temperatura). */
    public static function normalizarSensor(?string $tipo): string
    {
        return ($tipo !== null && SensorModel::tipoValido($tipo)) ? $tipo : 'temperatura';
    }

    /** Garante uma janela de horas válida (padrão: 24 h). */
    public static function normalizarHoras($horas): int
    {
        $horas = (int) $horas;
        return in_array($horas, self::HORAS_VALIDAS, true) ? $horas : 24;
    }

    // ── Consulta ─────────────────────────────────────────────────────────────

    /**
     * Série temporal de um tipo de sensor nas últimas $horas horas.
     *
     * Quando existe mais de um sensor do mesmo tipo (ex.: dois termômetros) ou
     * leituras muito frequentes, as medições são agrupadas em intervalos de
     * tempo e é devolvida a MÉDIA de cada intervalo — o gráfico fica limpo
     * (~120 pontos) independentemente do volume de dados.
     *
     * @return array<int, array{lido_em:string, valor:float, amostras:int}>
     *         ordenado do mais antigo para o mais recente
     */
    public function serie(string $tipo, int $horas): array
    {
        // Largura (segundos) de cada intervalo. É um inteiro calculado aqui,
        // não vem do usuário, então pode ser escrito direto no SQL.
        $passo = max(60, (int) ceil($horas * 3600 / self::PONTOS_ALVO));

        $stmt = $this->pdo->prepare("
            SELECT FROM_UNIXTIME(t.intervalo * $passo) AS lido_em,
                   ROUND(AVG(t.valor), 2)              AS valor,
                   COUNT(*)                            AS amostras
            FROM (
                SELECT FLOOR(UNIX_TIMESTAMP(l.lido_em) / $passo) AS intervalo,
                       l.valor
                FROM leitura_sensor l
                JOIN sensores s ON s.id = l.sensor_id
                WHERE s.tipo = :tipo
                  AND l.lido_em >= DATE_SUB(NOW(), INTERVAL $horas HOUR)
            ) t
            GROUP BY t.intervalo
            ORDER BY t.intervalo ASC
            LIMIT 1000
        ");
        $stmt->execute([':tipo' => $tipo]);

        $serie = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $serie[] = [
                'lido_em' => $linha['lido_em'],
                'valor' => (float) $linha['valor'],
                'amostras' => (int) $linha['amostras'],
            ];
        }
        return $serie;
    }
}
