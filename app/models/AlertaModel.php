<?php
/**
 * app/models/AlertaModel.php
 * Model: AlertaModel
 * Responsável pela comunicação com a tabela `alertas` do banco_tcc.
 * Segue o mesmo padrão do SensorModel (app/models/SensorModel.php).
 *
 * Cada alerta pertence a um sensor (`alertas.sensor_id`); o tipo do sensor
 * (temperatura, ruído...) é obtido com JOIN em `sensores` e devolvido na
 * coluna `sensor_tipo`. A estrutura da tabela está em sql/schema.sql.
 */

require_once __DIR__ . '/SensorModel.php'; // rótulo e unidade de cada tipo de sensor

class AlertaModel
{
    /** Severidades aceitas: chave gravada no banco → rótulo de exibição. */
    public const SEVERIDADES = [
        'critico' => 'Crítico',
        'atencao' => 'Atenção',
        'info' => 'Informativo',
    ];

    /**
     * LIMITES DOS ALERTAS AUTOMÁTICOS.
     *
     * Toda vez que uma leitura é registrada (LeituraController::registrar), o
     * valor é comparado com os limites do tipo do sensor; passou de um limite,
     * o alerta é criado sozinho. Para ajustar um limite, basta mudar o número.
     *
     *   'max' → alerta quando o valor fica ACIMA do número
     *   'min' → alerta quando o valor fica ABAIXO do número
     *
     * Se a leitura passar de mais de um limite, vale o mais grave (crítico).
     * Os valores seguem os exemplos já usados nos dados de demonstração
     * (ex.: temperatura crítica acima de 35 °C, ruído acima de 95 dB).
     */
    public const LIMITES = [
        'temperatura' => ['max' => ['atencao' => 30, 'critico' => 35]],
        'ruido' => ['max' => ['atencao' => 85, 'critico' => 95]],
        'qualidade_ar' => ['max' => ['atencao' => 100, 'critico' => 150]],
        'umidade' => [
            'min' => ['atencao' => 35, 'critico' => 20],
            'max' => ['atencao' => 80, 'critico' => 90],
        ],
        'pressao' => [
            'min' => ['atencao' => 1000, 'critico' => 990],
            'max' => ['atencao' => 1030, 'critico' => 1040],
        ],
        'uv' => ['max' => ['atencao' => 6, 'critico' => 8]],
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Rótulo da severidade (ex.: 'atencao' → 'Atenção'). */
    public static function rotuloSeveridade(string $severidade): string
    {
        return self::SEVERIDADES[$severidade] ?? $severidade;
    }

    /** A severidade informada existe? */
    public static function severidadeValida(string $severidade): bool
    {
        return array_key_exists($severidade, self::SEVERIDADES);
    }

    /**
     * Compara um valor com os limites do tipo de sensor (tabela LIMITES).
     *
     * @return array{severidade:string,mensagem:string}|null  o alerta que deve
     *         ser gerado, ou null se o valor está dentro dos limites
     */
    public static function avaliarLimite(string $tipo, float $valor): ?array
    {
        $regras = self::LIMITES[$tipo] ?? null;
        if ($regras === null) {
            return null;
        }

        $rotulo = SensorModel::rotulo($tipo);
        $unidade = SensorModel::unidade($tipo);

        // Do mais grave para o menos grave: o primeiro limite ultrapassado vence
        foreach (['critico', 'atencao'] as $severidade) {
            $adjetivo = $severidade === 'critico' ? 'crítico' : 'de atenção';

            if (isset($regras['max'][$severidade]) && $valor > $regras['max'][$severidade]) {
                $limite = self::formatarLimite($regras['max'][$severidade], $unidade);
                return [
                    'severidade' => $severidade,
                    'mensagem' => "$rotulo acima do limite $adjetivo de $limite",
                ];
            }
            if (isset($regras['min'][$severidade]) && $valor < $regras['min'][$severidade]) {
                $limite = self::formatarLimite($regras['min'][$severidade], $unidade);
                return [
                    'severidade' => $severidade,
                    'mensagem' => "$rotulo abaixo do mínimo $adjetivo de $limite",
                ];
            }
        }
        return null;
    }

    /** Número + unidade para as mensagens e para a tabela de limites (ex.: '35 °C', '35%', 'índice 8'). */
    public static function formatarLimite($numero, string $unidade): string
    {
        $texto = rtrim(rtrim(number_format((float) $numero, 2, ',', '.'), '0'), ',');
        return match ($unidade) {
            '%' => $texto . '%',
            'índice' => 'índice ' . $texto,
            '' => $texto,
            default => $texto . ' ' . $unidade,
        };
    }

    // ── Leitura ──────────────────────────────────────────────────────────────

    /**
     * Retorna os alertas mais recentes (pendentes primeiro).
     * Cada linha traz: id, sensor_id, sensor_tipo, sensor_nome, severidade,
     * mensagem, valor, resolvido, criado_em.
     */
    public function listarRecentes(int $limite = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.id, a.sensor_id, s.tipo AS sensor_tipo, s.nome AS sensor_nome,
                   a.severidade, a.mensagem, a.valor, a.resolvido, a.criado_em
            FROM alertas a
            JOIN sensores s ON s.id = a.sensor_id
            ORDER BY a.resolvido ASC, a.criado_em DESC, a.id DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a contagem de alertas PENDENTES agrupada por severidade.
     * Ex: ['critico' => 3, 'atencao' => 5, 'info' => 2]
     */
    public function contarPorSeveridade(): array
    {
        $stmt = $this->pdo->query("
            SELECT severidade, COUNT(*) AS total
            FROM alertas
            WHERE resolvido = 0
            GROUP BY severidade
        ");
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $contagem = ['critico' => 0, 'atencao' => 0, 'info' => 0];
        foreach ($linhas as $l) {
            $contagem[$l['severidade']] = (int) $l['total'];
        }
        return $contagem;
    }

    /**
     * Retorna um alerta pelo ID, ou false se não existir.
     */
    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT id, resolvido FROM alertas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Escrita ──────────────────────────────────────────────────────────────

    /**
     * Marca um alerta como resolvido e registra quem o resolveu.
     *
     * @param int      $id        ID do alerta
     * @param int|null $usuarioId administrador que resolveu (coluna `resolvido_por`)
     * @return bool    true se o alerta estava pendente e foi atualizado
     */
    public function marcarResolvido(int $id, ?int $usuarioId = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE alertas
            SET resolvido = 1, resolvido_por = :usuario
            WHERE id = :id AND resolvido = 0
        ");
        $stmt->execute([':usuario' => $usuarioId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cria um alerta e devolve o ID gerado. Os dados já devem ter sido
     * validados pelo AlertaController (sensor existente, severidade válida...).
     *
     * @param float|null $valor medição que originou o alerta (opcional)
     */
    public function criar(int $sensorId, string $severidade, string $mensagem, ?float $valor = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO alertas (sensor_id, severidade, mensagem, valor)
            VALUES (:sensor, :severidade, :mensagem, :valor)
        ");
        $stmt->execute([
            ':sensor' => $sensorId,
            ':severidade' => $severidade,
            ':mensagem' => $mensagem,
            ':valor' => $valor,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Procura um alerta PENDENTE igual (mesmo sensor, severidade e mensagem).
     * Evita que um sensor que continua fora do limite gere um alerta novo a
     * cada leitura. Devolve o ID do alerta existente, ou null.
     */
    public function buscarPendenteIgual(int $sensorId, string $severidade, string $mensagem): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM alertas
            WHERE sensor_id = :sensor AND severidade = :severidade
              AND mensagem = :mensagem AND resolvido = 0
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':sensor' => $sensorId, ':severidade' => $severidade, ':mensagem' => $mensagem]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
