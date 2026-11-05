<?php
require_once __DIR__ . '/../../includes/estoques.php';
require_once __DIR__ . '/LeituraModel.php';

/**
 * Estoques e a avaliação do seu estado.
 *
 * O estado de um estoque é descrito por TRÊS informações independentes:
 *   - condição ambiental  (normal / atenção / crítico / indisponível): vem só de leituras
 *     válidas e recentes (sensor ativo, dentro do prazo de comunicação do próprio sensor);
 *   - comunicação         (todos online / parcial / sem comunicação / sem sensores);
 *   - situação cadastral  (ativo / inativo).
 * Falha de comunicação nunca vira condição ambiental, e estoque sem sensores nunca é "normal".
 */
class EstoqueModel
{
    public const AMBIENTAL = ['normal' => 'Normal', 'atencao' => 'Atenção', 'critico' => 'Crítico', 'indisponivel' => 'Indisponível'];
    public const COMUNICACAO = ['todos_online' => 'Todos online', 'parcial' => 'Comunicação parcial', 'sem_comunicacao' => 'Sem comunicação', 'sem_sensores' => 'Sem sensores'];
    public const CADASTRAL = ['ativo' => 'Ativo', 'inativo' => 'Inativo'];

    public function __construct(private PDO $pdo) {}

    /**
     * Regra de classificação, isolada para poder ser testada sem banco.
     *
     * @param array[] $sensores  Linhas de LeituraModel::atuais() de UM estoque
     * @param int     $sensoresTotal  Sensores cadastrados (ativos e inativos)
     * @return array Campos acrescentados ao resumo do estoque
     */
    public static function classificar(array $sensores, string $statusEstoque, int $sensoresTotal): array
    {
        $ativos = array_values(array_filter($sensores, fn($s) => ($s['status'] ?? '') === 'ativo'));
        $online = array_values(array_filter($ativos, fn($s) => ($s['comunicacao'] ?? '') === 'online' && $s['ultimo_valor'] !== null));
        $mudos = array_values(array_filter($ativos, fn($s) => !in_array($s, $online, true)));
        $criticos = count(array_filter($online, fn($s) => ($s['situacao_ambiental'] ?? '') === 'critico'));
        $atencao = count(array_filter($online, fn($s) => ($s['situacao_ambiental'] ?? '') === 'atencao'));

        $ambiental = $criticos ? 'critico' : ($atencao ? 'atencao' : ($online ? 'normal' : 'indisponivel'));
        if ($statusEstoque !== 'ativo') $ambiental = 'indisponivel'; // estoque inativo não recebe leituras

        $comunicacao = !$ativos ? 'sem_sensores' : (!$online ? 'sem_comunicacao' : ($mudos ? 'parcial' : 'todos_online'));
        $rotuloComunicacao = self::COMUNICACAO[$comunicacao];
        if ($comunicacao === 'sem_sensores' && $sensoresTotal > 0) $rotuloComunicacao = 'Sem sensores ativos';

        return [
            'condicao_ambiental' => $ambiental,
            'condicao_ambiental_rotulo' => self::AMBIENTAL[$ambiental],
            'comunicacao_estado' => $comunicacao,
            'comunicacao_rotulo' => $rotuloComunicacao,
            'situacao_cadastral' => $statusEstoque === 'ativo' ? 'ativo' : 'inativo',
            'situacao_cadastral_rotulo' => self::CADASTRAL[$statusEstoque === 'ativo' ? 'ativo' : 'inativo'],
            'sensores_ativos' => count($ativos),
            'sensores_online' => $statusEstoque === 'ativo' ? count($online) : 0,
            'sensores_sem_comunicacao' => count($mudos),
            'sensores_sem_comunicacao_nomes' => array_column($mudos, 'nome'),
            'sensores_criticos' => $criticos,
            'sensores_atencao' => $atencao,
            'sensores_avaliados' => $statusEstoque === 'ativo' ? count($online) : 0,
            // Compatibilidade com clientes anteriores: agora contém SOMENTE a condição ambiental.
            'condicao' => self::AMBIENTAL[$ambiental],
        ];
    }

    public function listarTodos(): array
    {
        $sql = "SELECT e.*,
          (SELECT COUNT(*) FROM sensores s WHERE s.estoque_id=e.id) AS sensores_total,
          (SELECT COUNT(*) FROM alertas a WHERE a.estoque_id=e.id AND a.status IN ('pendente','reconhecido')) AS alertas_ativos,
          (SELECT COUNT(*) FROM alertas a WHERE a.estoque_id=e.id AND a.status='pendente' AND a.severidade='critico') AS alertas_criticos_pendentes
          FROM estoques e WHERE " . escopoEstoque('e.id') . ' ORDER BY e.status, e.nome';
        $rows = $this->pdo->query($sql)->fetchAll();
        $porEstoque = [];
        foreach ((new LeituraModel($this->pdo))->atuais() as $sensor) $porEstoque[(int) $sensor['estoque_id']][] = $sensor;
        foreach ($rows as &$row) {
            $sensores = $porEstoque[(int) $row['id']] ?? [];
            $row = array_merge($row, self::classificar($sensores, $row['status'], (int) $row['sensores_total']));
            $leituras = array_filter(array_column($sensores, 'lido_em'));
            $row['ultima_leitura'] = $leituras ? max($leituras) : null;
        }
        return $rows;
    }

    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT e.* FROM estoques e WHERE e.id=? AND ' . escopoEstoque('e.id'));
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function salvar(array $dados, ?int $id = null): int
    {
        if ($id) {
            $stmt = $this->pdo->prepare('UPDATE estoques SET nome=?,descricao=?,localizacao=?,status=? WHERE id=?');
            $stmt->execute([$dados['nome'], $dados['descricao'], $dados['localizacao'], $dados['status'], $id]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO estoques (nome,descricao,localizacao,status) VALUES (?,?,?,?)');
            $stmt->execute([$dados['nome'], $dados['descricao'], $dados['localizacao'], $dados['status']]);
            $id = (int) $this->pdo->lastInsertId();
        }
        return $id;
    }
}
