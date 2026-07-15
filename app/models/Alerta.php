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

    // ── Dados de exemplo (base para o gráfico funcionar sem o banco pronto) ──

    /**
     * Enquanto a tabela `alertas` não existe/está vazia no banco real,
     * este método devolve um conjunto de dados de exemplo para que a
     * tela e os gráficos tenham algo para exibir.
     */
    public function gerarDadosExemplo(): array
    {
        $agora = time();
        return [
            ['id' => 1, 'sensor_tipo' => 'temperatura', 'severidade' => 'critico', 'mensagem' => 'Temperatura acima do limite de segurança', 'valor' => 78.4, 'resolvido' => 0, 'criado_em' => date('Y-m-d H:i:s', $agora - 600)],
            ['id' => 2, 'sensor_tipo' => 'qualidade_ar', 'severidade' => 'atencao', 'mensagem' => 'Qualidade do ar em nível moderado',        'valor' => 132,  'resolvido' => 0, 'criado_em' => date('Y-m-d H:i:s', $agora - 1800)],
            ['id' => 3, 'sensor_tipo' => 'ruido',        'severidade' => 'atencao', 'mensagem' => 'Ruído acima do recomendado para o turno',   'valor' => 87.2, 'resolvido' => 0, 'criado_em' => date('Y-m-d H:i:s', $agora - 3600)],
            ['id' => 4, 'sensor_tipo' => 'umidade',      'severidade' => 'info',    'mensagem' => 'Umidade retornou ao patamar normal',        'valor' => 54.1, 'resolvido' => 1, 'criado_em' => date('Y-m-d H:i:s', $agora - 7200)],
            ['id' => 5, 'sensor_tipo' => 'pressao',      'severidade' => 'info',    'mensagem' => 'Leitura de pressão registrada normalmente', 'valor' => 1013, 'resolvido' => 1, 'criado_em' => date('Y-m-d H:i:s', $agora - 10800)],
            ['id' => 6, 'sensor_tipo' => 'uv',           'severidade' => 'critico', 'mensagem' => 'Índice UV muito alto na área externa',     'valor' => 11.3, 'resolvido' => 0, 'criado_em' => date('Y-m-d H:i:s', $agora - 14400)],
        ];
    }

    /**
     * Mesma lógica de contarPorSeveridade(), mas a partir dos dados de exemplo.
     */
    public function contarPorSeveridadeExemplo(): array
    {
        $contagem = ['critico' => 0, 'atencao' => 0, 'info' => 0];
        foreach ($this->gerarDadosExemplo() as $a) {
            if ((int) $a['resolvido'] === 0) {
                $contagem[$a['severidade']]++;
            }
        }
        return $contagem;
    }
}
