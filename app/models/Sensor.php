<?php
/**
 * Model: SensorModel
 * Responsável por toda a comunicação com a tabela `sensores` do banco_tcc.
 *
 * DDL sugerido para criar a tabela no MySQL Workbench:
 *
 * CREATE TABLE sensores (
 *     id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     nome        VARCHAR(100)  NOT NULL,
 *     tipo        ENUM('temperatura','ruido','qualidade_ar','umidade','pressao','uv') NOT NULL,
 *     localizacao VARCHAR(150)  DEFAULT NULL,
 *     status      ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
 *     criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

class SensorModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Leitura ──────────────────────────────────────────────────────────────

    /**
     * Retorna todos os sensores ordenados por status (ativo primeiro) e nome.
     */
    public function listarTodos(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nome, tipo, localizacao, status, criado_em
            FROM sensores
            ORDER BY status DESC, nome ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna um sensor pelo ID, ou false se não existir.
     */
    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT id, nome, tipo, localizacao, status, criado_em
            FROM sensores
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Escrita ──────────────────────────────────────────────────────────────

    /**
     * Insere um novo sensor e retorna o ID gerado.
     *
     * @param array $dados ['nome', 'tipo', 'localizacao', 'status']
     */
    public function cadastrar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO sensores (nome, tipo, localizacao, status, criado_em)
            VALUES (:nome, :tipo, :localizacao, :status, NOW())
        ");
        $stmt->execute([
            ':nome'        => $dados['nome'],
            ':tipo'        => $dados['tipo'],
            ':localizacao' => $dados['localizacao'] ?? null,
            ':status'      => $dados['status'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza apenas o status de um sensor.
     *
     * @param int    $id     ID do sensor
     * @param string $status 'ativo' ou 'inativo'
     */
    public function atualizarStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE sensores SET status = :status WHERE id = :id
        ");
        $stmt->execute([':status' => $status, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
