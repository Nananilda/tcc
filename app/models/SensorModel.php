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

require_once __DIR__ . '/../../includes/estoques.php';

class SensorModel
{
    /**
     * Tipos de sensor aceitos: chave gravada no banco (ENUM `sensores.tipo`)
     * → rótulo de exibição e unidade. Para criar um novo tipo, inclua-o aqui E
     * no ENUM da tabela.
     */
    public const TIPOS = [
        'temperatura' => ['label' => 'Temperatura', 'unidade' => '°C'],
        'umidade' => ['label' => 'Umidade', 'unidade' => '%'],
        'qualidade_ar' => ['label' => 'Qualidade do ar', 'unidade' => 'AQI'],
        'ruido' => ['label' => 'Ruído', 'unidade' => 'dB'],
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

    /** Consulta com escopo de estoque e última leitura real. */
    private function consulta(): string
    {
        return "SELECT s.*, e.nome AS estoque_nome, e.status AS estoque_status,
                l.valor AS ultimo_valor, l.lido_em AS ultima_leitura
                FROM sensores s JOIN estoques e ON e.id=s.estoque_id
                LEFT JOIN leitura_sensor l ON l.id=(SELECT ll.id FROM leitura_sensor ll
                    WHERE ll.sensor_id=s.id AND ll.estoque_id=s.estoque_id ORDER BY ll.lido_em DESC,ll.id DESC LIMIT 1)";
    }

    public static function situacaoComunicacao(array $sensor): string
    {
        if (($sensor['status'] ?? '') !== 'ativo' || ($sensor['estoque_status'] ?? 'ativo') !== 'ativo') return 'inativo';
        $ultima = $sensor['ultima_leitura'] ?? $sensor['ultima_comunicacao'] ?? null;
        return $ultima && strtotime($ultima) >= time() - (int) ($sensor['intervalo_comunicacao'] ?? 300)
            ? 'online' : 'sem_comunicacao';
    }

    public function listarTodos(array $filtros = []): array
    {
        $sql = $this->consulta() . ' WHERE ' . escopoEstoque('s.estoque_id');
        $params = [];
        foreach (['estoque_id', 'id'] as $campo) {
            if (!empty($filtros[$campo])) {
                $sql .= " AND s.$campo = :$campo";
                $params[$campo] = (int) $filtros[$campo];
            }
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY s.status,s.nome');
        $stmt->execute($params);
        return array_map(function ($s) { $s['comunicacao'] = self::situacaoComunicacao($s); return $s; }, $stmt->fetchAll());
    }

    public function buscarPorId(int $id, bool $respeitarEscopo = true): array|false
    {
        $stmt = $this->pdo->prepare($this->consulta() . ' WHERE s.id=:id' .
            ($respeitarEscopo ? ' AND ' . escopoEstoque('s.estoque_id') : ''));
        $stmt->execute(['id' => $id]);
        $s = $stmt->fetch();
        if ($s) $s['comunicacao'] = self::situacaoComunicacao($s);
        return $s;
    }

    public function contarPorStatus(): array
    {
        $sensores = $this->listarTodos();
        return ['total' => count($sensores),
            'ativos' => count(array_filter($sensores, fn($s) => $s['status'] === 'ativo')),
            'online' => count(array_filter($sensores, fn($s) => $s['comunicacao'] === 'online'))];
    }

    private const CAMPOS = ['nome','tipo','localizacao','status','estoque_id',
        'limite_atencao_min','limite_atencao_max','limite_critico_min','limite_critico_max','intervalo_comunicacao'];

    public function cadastrar(array $dados): int
    {
        $campos = implode(',', self::CAMPOS);
        $marcadores = ':' . implode(',:', self::CAMPOS);
        $stmt = $this->pdo->prepare("INSERT INTO sensores ($campos) VALUES ($marcadores)");
        $stmt->execute(array_intersect_key($dados, array_flip(self::CAMPOS)));
        return (int) $this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): void
    {
        $sql = implode(',', array_map(fn($c) => "$c=:$c", self::CAMPOS));
        $stmt = $this->pdo->prepare("UPDATE sensores SET ultima_comunicacao=IF(estoque_id=:novo_estoque,ultima_comunicacao,NULL),$sql WHERE id=:id");
        $stmt->execute(array_intersect_key($dados, array_flip(self::CAMPOS)) + ['id' => $id,'novo_estoque'=>$dados['estoque_id']]);
    }

    public function atualizarStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE sensores SET status=:status WHERE id=:id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Compatibilidade da ação antiga: desativa, sem apagar leituras/alertas. */
    public function excluir(int $id): bool
    {
        return $this->atualizarStatus($id, 'inativo');
    }
}
