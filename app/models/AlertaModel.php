<?php
require_once __DIR__ . '/SensorModel.php';
require_once __DIR__ . '/../../includes/estoques.php';

class AlertaModel
{
    public const SEVERIDADES = [
        'critico' => 'Crítico',
        'atencao' => 'Atenção',
        'info' => 'Informativo',
    ];

    /** Valores iniciais para migração/novos sensores; a avaliação usa a configuração individual. */
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

    public const STATUS = ['pendente'=>'Pendente', 'reconhecido'=>'Reconhecido', 'normalizado'=>'Normalizado', 'resolvido'=>'Resolvido'];
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public static function rotuloSeveridade(string $valor): string { return self::SEVERIDADES[$valor] ?? $valor; }
    public static function severidadeValida(string $valor): bool { return isset(self::SEVERIDADES[$valor]); }

    /** Array de sensor usa somente os limites configurados. String mantém compatibilidade do catálogo. */
    public static function avaliarLimite(string|array $sensor, float $valor): ?array
    {
        if (is_string($sensor)) {
            $tipo = $sensor;
            $sensor = ['tipo'=>$tipo];
            foreach (['atencao','critico'] as $grau) foreach (['min','max'] as $lado) {
                $sensor["limite_{$grau}_{$lado}"] = self::LIMITES[$tipo][$lado][$grau] ?? null;
            }
        }
        $tipo = $sensor['tipo'];
        foreach (['critico','atencao'] as $grau) foreach (['min','max'] as $lado) {
            $limite = $sensor["limite_{$grau}_{$lado}"] ?? null;
            if ($limite !== null && ($lado === 'min' ? $valor < (float)$limite : $valor > (float)$limite)) {
                $texto = self::formatarLimite($limite, SensorModel::unidade($tipo));
                return ['severidade'=>$grau, 'limite_ultrapassado'=>(float)$limite,
                    'mensagem'=>SensorModel::rotulo($tipo) . ($lado === 'min' ? ' abaixo' : ' acima') . ' do limite ' . ($grau === 'critico' ? 'crítico' : 'de atenção') . ' de ' . $texto];
            }
        }
        return null;
    }
    public static function formatarLimite($numero, string $unidade): string
    {
        return rtrim(rtrim(number_format((float)$numero,2,',','.'),'0'),',') . ($unidade === '%' ? '%' : ($unidade ? ' '.$unidade : ''));
    }
    public static function validarFiltros(array $entrada): array
    {
        $f = [];
        foreach (['estoque_id','sensor_id'] as $campo) {
            if (($entrada[$campo] ?? '') === '' || in_array($entrada[$campo]??null,[0,'0'],true)) continue;
            $id = filter_var($entrada[$campo], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($id === false) throw new InvalidArgumentException('Identificador de estoque ou sensor inválido.');
            $f[$campo] = $id;
        }
        foreach (['severidade'=>self::SEVERIDADES,'status'=>self::STATUS] as $campo=>$permitidos) {
            if (($entrada[$campo] ?? '') === '') continue;
            if (!is_string($entrada[$campo]) || !isset($permitidos[$entrada[$campo]])) throw new InvalidArgumentException('Filtro de alerta inválido.');
            $f[$campo] = $entrada[$campo];
        }
        foreach (['data_inicial','data_final'] as $campo) {
            $v = $entrada[$campo] ?? '';
            if ($v === '') continue;
            $data = is_string($v) ? DateTimeImmutable::createFromFormat('!Y-m-d',$v) : false;
            if (!$data || $data->format('Y-m-d') !== $v) throw new InvalidArgumentException('Data inválida.');
            $f[$campo] = $v;
        }
        if (isset($f['data_inicial'],$f['data_final']) && $f['data_inicial'] > $f['data_final']) throw new InvalidArgumentException('A data final deve ser posterior à inicial.');
        return $f;
    }
    private function filtroSql(array $f, array &$parametros): string
    {
        $where = [escopoEstoque('a.estoque_id')];
        foreach (['estoque_id','sensor_id','severidade','status'] as $campo) if (isset($f[$campo])) {
            $where[] = "a.$campo = :$campo"; $parametros[":$campo"] = $f[$campo];
        }
        if (isset($f['data_inicial'])) { $where[] = 'a.criado_em >= :inicio'; $parametros[':inicio'] = $f['data_inicial'].' 00:00:00'; }
        if (isset($f['data_final'])) { $where[] = 'a.criado_em < :fim'; $parametros[':fim'] = (new DateTimeImmutable($f['data_final']))->modify('+1 day')->format('Y-m-d'); }
        return implode(' AND ',$where);
    }
    public function listarRecentes(int $limite = 50, array $filtros = [], int $pagina = 1, bool $todas = false, bool $cronologico = false, ?int $inicio = null): array
    {
        $param = []; $where = $this->filtroSql(self::validarFiltros($filtros),$param);
        $limite = max(1,min(200,$limite)); $offset = (max(1,min(100000,$pagina))-1)*$limite;
        $offset=$inicio!==null?max(0,$inicio):$offset;
        $ordem=$cronologico?'a.criado_em DESC,a.id DESC':"a.resolvido ASC, FIELD(a.severidade,'critico','atencao','info'),a.criado_em DESC,a.id DESC";
        $paginacao=$todas?'':" LIMIT $limite OFFSET $offset";
        $q = $this->pdo->prepare("SELECT a.*,s.tipo AS sensor_tipo,s.nome AS sensor_nome,e.nome AS estoque_nome,
            ur.nome AS reconhecido_nome,us.nome AS resolvido_nome
            FROM alertas a JOIN sensores s ON s.id=a.sensor_id JOIN estoques e ON e.id=a.estoque_id
            LEFT JOIN usuario ur ON ur.id=a.reconhecido_por LEFT JOIN usuario us ON us.id=a.resolvido_por
            WHERE $where ORDER BY $ordem $paginacao");
        $q->execute($param); return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function contarTodos(array $filtros = []): int
    {
        $param=[]; $where=$this->filtroSql(self::validarFiltros($filtros),$param);
        $q=$this->pdo->prepare("SELECT COUNT(*) FROM alertas a WHERE $where"); $q->execute($param); return (int)$q->fetchColumn();
    }
    public function contarPorSeveridade(array $filtros = []): array
    {
        $param=[]; $where=$this->filtroSql(self::validarFiltros($filtros),$param);
        $q=$this->pdo->prepare("SELECT a.severidade,COUNT(*) AS total FROM alertas a WHERE $where AND a.status IN ('pendente','reconhecido') GROUP BY a.severidade");
        $q->execute($param); $contagem=['critico'=>0,'atencao'=>0,'info'=>0];
        foreach ($q->fetchAll() as $a) $contagem[$a['severidade']] = (int)$a['total']; return $contagem;
    }
    public function buscarPorId(int $id): array|false
    {
        $q=$this->pdo->prepare('SELECT a.* FROM alertas a WHERE a.id=:id AND '.escopoEstoque('a.estoque_id'));
        $q->execute([':id'=>$id]); return $q->fetch();
    }
    public function reconhecer(int $id, int $usuarioId): bool
    {
        $q=$this->pdo->prepare("UPDATE alertas SET status='reconhecido', reconhecido_por=:usuario,reconhecido_em=NOW() WHERE id=:id AND status='pendente'");
        $q->execute([':usuario'=>$usuarioId,':id'=>$id]); return $q->rowCount()>0;
    }
    public function marcarResolvido(int $id, ?int $usuarioId = null, string $observacoes = ''): bool
    {
        $q=$this->pdo->prepare("UPDATE alertas SET resolvido=1,status='resolvido',resolvido_por=:usuario,resolvido_em=NOW(),observacoes=:observacoes WHERE id=:id AND resolvido=0");
        $q->execute([':usuario'=>$usuarioId,':id'=>$id,':observacoes'=>$observacoes]); return $q->rowCount()>0;
    }
    public function criar(int $sensorId, string $severidade, string $mensagem, ?float $valor = null): int
    {
        $q=$this->pdo->prepare("INSERT INTO alertas(sensor_id,estoque_id,severidade,mensagem,valor) SELECT id,estoque_id,:grau,:mensagem,:valor FROM sensores WHERE id=:sensor");
        $q->execute([':grau'=>$severidade,':mensagem'=>$mensagem,':valor'=>$valor,':sensor'=>$sensorId]); return (int)$this->pdo->lastInsertId();
    }
    public function buscarPendenteIgual(int $sensorId,string $severidade,string $mensagem): ?int
    {
        $q=$this->pdo->prepare("SELECT id FROM alertas WHERE sensor_id=:sensor AND severidade=:grau AND mensagem=:mensagem AND resolvido=0 ORDER BY id DESC LIMIT 1");
        $q->execute([':sensor'=>$sensorId,':grau'=>$severidade,':mensagem'=>$mensagem]); $id=$q->fetchColumn(); return $id===false ? null : (int)$id;
    }
}
