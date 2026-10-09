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

class AlertaModel
{
    /** Severidades aceitas: chave gravada no banco → rótulo de exibição. */
    public const SEVERIDADES = [
        'critico' => 'Crítico',
        'atencao' => 'Atenção',
        'info' => 'Informativo',
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
}
