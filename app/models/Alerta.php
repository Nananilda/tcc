<?php
/**
 * Model: AlertaModel
 * Responsável pela comunicação com a tabela `alertas` do banco_tcc.
 * Segue o mesmo padrão do SensorModel (app/models/Sensor.php).
 *
 * DDL sugerido para criar a tabela no MySQL Workbench:
 *
 * CREATE TABLE alertas (
 *     id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     sensor_tipo ENUM('temperatura','ruido','qualidade_ar','umidade','pressao','uv') NOT NULL,
 *     severidade  ENUM('info','atencao','critico') NOT NULL DEFAULT 'info',
 *     mensagem    VARCHAR(200) NOT NULL,
 *     valor       DECIMAL(10,2) DEFAULT NULL,
 *     resolvido   TINYINT(1)   NOT NULL DEFAULT 0,
 *     criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

class AlertaModel
{
    private PDO $pdo;

    public const SEVERIDADES = ['info', 'atencao', 'critico'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Leitura ──────────────────────────────────────────────────────────────

    /**
     * Retorna os alertas mais recentes (não resolvidos primeiro).
     */
    public function listarRecentes(int $limite = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, sensor_tipo, severidade, mensagem, valor, resolvido, criado_em
            FROM alertas
            ORDER BY resolvido ASC, criado_em DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a contagem de alertas agrupada por severidade.
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

    // ── Escrita ──────────────────────────────────────────────────────────────

    public function marcarResolvido(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE alertas SET resolvido = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
