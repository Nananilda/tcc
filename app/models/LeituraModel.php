<?php
require_once __DIR__ . '/SensorModel.php';
require_once __DIR__ . '/../../includes/estoques.php';

class LeituraModel
{
    public const HORAS_VALIDAS = [6, 12, 24, 48, 168];
    public const ROTULOS_PERIODO = [6=>'6 h',12=>'12 h',24=>'24 h',48=>'48 h',168=>'7 dias'];
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public static function rotuloJanela(int $horas): string { return $horas === 168 ? 'últimos 7 dias' : "últimas $horas h"; }
    public static function normalizarSensor(?string $tipo): string { return SensorModel::tipoValido($tipo ?? '') ? $tipo : 'temperatura'; }
    public static function normalizarHoras($horas): int { return in_array((int)$horas,self::HORAS_VALIDAS,true) ? (int)$horas : 24; }
    public function serie(string $tipo,int $horas,array $filtros=[]): array
    {
        $params = [':tipo'=>$tipo];
        $where = 's.tipo = :tipo AND ' . escopoEstoque('l.estoque_id');
        if (!empty($filtros['data_ini']) && !empty($filtros['data_fim'])) {
            $where .= ' AND l.lido_em BETWEEN :ini AND :fim';
            $params[':ini']=$filtros['data_ini'].' 00:00:00'; $params[':fim']=$filtros['data_fim'].' 23:59:59';
            $segundos=max(3600,strtotime($params[':fim'])-strtotime($params[':ini']));
        } else { $where .= ' AND l.lido_em >= DATE_SUB(NOW(), INTERVAL '.(int)$horas.' HOUR)'; $segundos=$horas*3600; }
        foreach (['estoque_id'=>'l.estoque_id','sensor_id'=>'l.sensor_id'] as $campo=>$coluna) {
            if (!empty($filtros[$campo])) { $where.=" AND $coluna = :$campo"; $params[":$campo"]=(int)$filtros[$campo]; }
        }
        foreach (['val_min'=>'>=', 'val_max'=>'<='] as $campo=>$operador) {
            if (isset($filtros[$campo]) && $filtros[$campo] !== '') { $where .= " AND l.valor $operador :$campo"; $params[":$campo"] = (float)$filtros[$campo]; }
        }
        // A resolução acompanha os dados existentes, mesmo ao consultar todo o histórico.
        $extremos=$this->pdo->prepare("SELECT MIN(l.lido_em) inicio,MAX(l.lido_em) fim FROM leitura_sensor l JOIN sensores s ON s.id=l.sensor_id WHERE $where");
        $extremos->execute($params);$faixa=$extremos->fetch();
        if(!$faixa['inicio'])return [];
        $segundos=max(1,strtotime($faixa['fim'])-strtotime($faixa['inicio']));
        $passo=max(1,(int)ceil($segundos/240));
        $sql="SELECT l.sensor_id, s.nome AS sensor_nome, s.tipo AS sensor_tipo, l.estoque_id,
            MIN(l.lido_em) AS inicio_intervalo, MAX(l.lido_em) AS lido_em, ROUND(AVG(l.valor),2) AS valor, COUNT(*) AS amostras,
            MAX(l.limite_atencao_min) AS limite_atencao_min,MAX(l.limite_atencao_max) AS limite_atencao_max,
            MAX(l.limite_critico_min) AS limite_critico_min,MAX(l.limite_critico_max) AS limite_critico_max
            FROM leitura_sensor l JOIN sensores s ON s.id=l.sensor_id WHERE $where
            GROUP BY l.sensor_id,s.nome,s.tipo,l.estoque_id,FLOOR(UNIX_TIMESTAMP(l.lido_em)/$passo),
            l.limite_atencao_min,l.limite_atencao_max,l.limite_critico_min,l.limite_critico_max
            ORDER BY lido_em,l.sensor_id";
        $st=$this->pdo->prepare($sql); $st->execute($params);
        $rows=$st->fetchAll();
        foreach ($rows as &$row) { $row['valor']=(float)$row['valor'];$row['amostras']=(int)$row['amostras'];$row['unidade']=SensorModel::unidade($tipo); }
        return $rows;
    }
    public function atuais(int $estoqueId=0,int $sensorId=0): array
    {
        $where=escopoEstoque('s.estoque_id');$params=[];
        foreach(['estoque_id'=>$estoqueId,'id'=>$sensorId] as $campo=>$id) if($id>0){$where.=" AND s.$campo=:$campo";$params[":$campo"]=$id;}
        $st=$this->pdo->prepare("SELECT s.*, e.nome AS estoque_nome, l.valor AS ultimo_valor,l.lido_em,
            l.situacao AS situacao_ambiental,
            CASE WHEN s.status='inativo' OR e.status='inativo' THEN 'inativo'
            WHEN l.lido_em IS NULL OR TIMESTAMPDIFF(SECOND,l.lido_em,NOW())>s.intervalo_comunicacao THEN 'sem_comunicacao'
            ELSE 'online' END AS comunicacao
            FROM sensores s JOIN estoques e ON e.id=s.estoque_id
            LEFT JOIN leitura_sensor l ON l.id=(SELECT x.id FROM leitura_sensor x WHERE x.sensor_id=s.id AND x.estoque_id=s.estoque_id ORDER BY x.lido_em DESC,x.id DESC LIMIT 1)
            WHERE $where ORDER BY CASE s.tipo WHEN 'temperatura' THEN 1 WHEN 'umidade' THEN 2 WHEN 'qualidade_ar' THEN 3 ELSE 4 END,s.nome");
        $st->execute($params);$rows=$st->fetchAll();
        foreach($rows as &$row) { $row['unidade']=SensorModel::unidade($row['tipo']); $row['ultimo_valor']=$row['ultimo_valor']===null?null:(float)$row['ultimo_valor']; }
        return $rows;
    }
    public function registrar(int $sensorId,float $valor,?array $sensor=null): int
    {
        if($sensor===null){$st=$this->pdo->prepare('SELECT * FROM sensores WHERE id=?');$st->execute([$sensorId]);$sensor=$st->fetch();}
        if(!$sensor) throw new InvalidArgumentException('Sensor não encontrado.');
        $situacao='normal';
        foreach(['atencao','critico'] as $nivel) if(($sensor["limite_{$nivel}_min"]!==null && $valor<$sensor["limite_{$nivel}_min"])||($sensor["limite_{$nivel}_max"]!==null && $valor>$sensor["limite_{$nivel}_max"])) $situacao=$nivel;
        $st=$this->pdo->prepare('INSERT INTO leitura_sensor (sensor_id,estoque_id,valor,situacao,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max,lido_em) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $st->execute([$sensorId,$sensor['estoque_id'],$valor,$situacao,$sensor['limite_atencao_min'],$sensor['limite_atencao_max'],$sensor['limite_critico_min'],$sensor['limite_critico_max']]);
        return (int)$this->pdo->lastInsertId();
    }
}
