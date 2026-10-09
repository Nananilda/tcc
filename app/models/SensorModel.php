<?php
/**
 * app/models/SensorModel.php
 * Model: SensorModel
 * Responsável por toda a comunicação com a tabela `sensores` do banco_tcc e
 * também pelo CATÁLOGO dos tipos de sensor (rótulo e unidade de medida), que é
 * a fonte única usada por gráficos, relatórios, alertas e pela API.
 *
 * A estrutura da tabela está em sql/schema.sql.
 */

class SensorModel
{
    /**
     * Tipos de sensor aceitos: chave gravada no banco (ENUM `sensores.tipo`)
     * → rótulo de exibição e unidade. Para criar um novo tipo, inclua-o aqui E
     * no ENUM da tabela.
     */
    public const TIPOS = [
        'temperatura' => ['label' => 'Temperatura', 'unidade' => '°C'],
        'ruido' => ['label' => 'Ruído', 'unidade' => 'dB'],
        'qualidade_ar' => ['label' => 'Qualidade do Ar', 'unidade' => 'AQI'],
        'umidade' => ['label' => 'Umidade', 'unidade' => '%'],
        'pressao' => ['label' => 'Pressão', 'unidade' => 'hPa'],
        'uv' => ['label' => 'UV', 'unidade' => 'índice'],
    ];

    /** Situações possíveis de um sensor. */
    public const STATUS = ['ativo', 'inativo'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Catálogo de tipos (métodos estáticos, não consultam o banco) ─────────

    /** O tipo informado existe no catálogo? */
    public static function tipoValido(string $tipo): bool
    {
        return array_key_exists($tipo, self::TIPOS);
    }

    /** Rótulo de exibição (ex.: 'Qualidade do Ar'); devolve a própria chave se desconhecido. */
    public static function rotulo(string $tipo): string
    {
        return self::TIPOS[$tipo]['label'] ?? $tipo;
    }

    /** Unidade de medida (ex.: '°C'). */
    public static function unidade(string $tipo): string
    {
        return self::TIPOS[$tipo]['unidade'] ?? '';
    }

    /** Rótulo + unidade (ex.: 'Temperatura (°C)'), usado em seletores e relatórios. */
    public static function rotuloComUnidade(string $tipo): string
    {
        $unidade = self::unidade($tipo);
        return self::rotulo($tipo) . ($unidade !== '' ? " ($unidade)" : '');
    }

    // ── Leitura ──────────────────────────────────────────────────────────────

    /**
     * Retorna todos os sensores ordenados por status (ativo primeiro) e nome.
     * Obs.: em colunas ENUM o MySQL ordena pela posição declarada no ENUM
     * ('ativo' = 1, 'inativo' = 2), por isso a ordem crescente.
     */
    public function listarTodos(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nome, tipo, localizacao, status, criado_em
            FROM sensores
            ORDER BY status ASC, nome ASC
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

    /**
     * Conta os sensores em uma única consulta (usado pelo painel).
     *
     * @return array{ativos:int,total:int}
     */
    public function contarPorStatus(): array
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(status = 'ativo'), 0) AS ativos
            FROM sensores
        ");
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['ativos' => (int) $linha['ativos'], 'total' => (int) $linha['total']];
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
            ':nome' => $dados['nome'],
            ':tipo' => $dados['tipo'],
            ':localizacao' => $dados['localizacao'] ?? null,
            ':status' => $dados['status'],
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
