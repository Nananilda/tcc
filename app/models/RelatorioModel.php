<?php
require_once __DIR__.'/SensorModel.php';
require_once __DIR__.'/../../includes/estoques.php';
class RelatorioModel
{
    public const LIMITE_LEITURAS=200;
    private PDO $pdo;
    public function __construct(PDO $pdo){$this->pdo=$pdo;}
    public function getSensorTipos(): array{return array_keys(SensorModel::TIPOS);}
    public function getLabels(): array{return array_map(fn($v)=>$v['label'].' ('.$v['unidade'].')',SensorModel::TIPOS);}
    public function getLabelPorTipo(string $tipo): string{return SensorModel::rotuloComUnidade($tipo);}
    public function filtrosDaRequisicao(array $entrada): array
    {
        $f=['sensor'=>'','data_ini'=>date('Y-m-d',strtotime('-7 days')),'data_fim'=>date('Y-m-d'),'val_min'=>'','val_max'=>'','estoque_id'=>0,'sensor_id'=>0,'pagina'=>1,'por_pagina'=>200,'horas'=>0];
        foreach(['data_ini','data_fim'] as $key) if(isset($entrada[$key]) && $entrada[$key]!==''){
            if(!is_string($entrada[$key]))throw new InvalidArgumentException('Data inválida.');
            $d=DateTime::createFromFormat('!Y-m-d',$entrada[$key]);
            if(!$d||$d->format('Y-m-d')!==$entrada[$key])throw new InvalidArgumentException('Data inválida.');
            $f[$key]=$entrada[$key];
        }
        if(empty($entrada['data_ini'])&&empty($entrada['data_fim'])&&in_array((int)($entrada['horas']??0),[6,12,24,48,168],true)) { $f['horas']=(int)$entrada['horas'];$f['data_ini']='';$f['data_fim']=''; }
        if($f['data_ini']>$f['data_fim'])throw new InvalidArgumentException('A data inicial deve ser anterior à data final.');
        $tipo=$entrada['sensor']??$entrada['tipo']??'';
        if(!is_string($tipo)||($tipo!==''&&!SensorModel::tipoValido($tipo)))throw new InvalidArgumentException('Tipo de medição inválido.');
        $f['sensor']=$tipo;
        foreach(['estoque_id','sensor_id','pagina'] as $key)if(isset($entrada[$key])&&$entrada[$key]!==''){
            if(filter_var($entrada[$key],FILTER_VALIDATE_INT)===false||(int)$entrada[$key]<0)throw new InvalidArgumentException('Identificador inválido.');
            $f[$key]=(int)$entrada[$key];
        }
        $f['por_pagina']=in_array((int)($entrada['por_pagina']??200),[25,50,100,200],true)?(int)($entrada['por_pagina']??200):200;
        $f['pagina']=max(1,min(100000,$f['pagina']));
        if($f['estoque_id']&&!podeAcessarEstoque($this->pdo,$f['estoque_id']))throw new InvalidArgumentException('Estoque indisponível ou sem permissão.');
        if($f['sensor_id']){
            $st=$this->pdo->prepare('SELECT s.id FROM sensores s WHERE s.id=? AND ('.escopoEstoque('s.estoque_id').' OR EXISTS (SELECT 1 FROM leitura_sensor lh WHERE lh.sensor_id=s.id AND '.escopoEstoque('lh.estoque_id').'))');$st->execute([$f['sensor_id']]);
            if(!$st->fetchColumn())throw new InvalidArgumentException('Sensor indisponível ou sem permissão.');
        }
        foreach(['val_min','val_max'] as $key)if(isset($entrada[$key])&&$entrada[$key]!==''){
            $v=lerNumero($entrada[$key]);if($v===null)throw new InvalidArgumentException('Faixa de valores inválida.');$f[$key]=(string)$v;
        }
        if($f['val_min']!==''&&$f['val_max']!==''&&(float)$f['val_min']>(float)$f['val_max'])throw new InvalidArgumentException('O valor mínimo deve ser menor ou igual ao máximo.');
        return $f;
    }
    public function montarFiltros(string $dataIni,string $dataFim,string $sensor='',string $valMin='',string $valMax='',int $estoqueId=0,int $sensorId=0,int $horas=0): array
    {
        $where='WHERE l.lido_em BETWEEN :ini AND :fim AND '.escopoEstoque('l.estoque_id');
        $params=[':ini'=>$dataIni.' 00:00:00',':fim'=>$dataFim.' 23:59:59'];
        if($horas>0){$where='WHERE l.lido_em >= DATE_SUB(NOW(), INTERVAL '.(int)$horas.' HOUR) AND '.escopoEstoque('l.estoque_id');$params=[];}
        foreach(['s.tipo'=>['tipo',$sensor],'l.estoque_id'=>['estoque',$estoqueId],'l.sensor_id'=>['sensor_id',$sensorId]] as $column=>[$key,$value])if($value!==''&&$value!==0){$where.=" AND $column=:$key";$params[":$key"]=$value;}
        if($valMin!==''){$where.=' AND l.valor>=:min';$params[':min']=(float)$valMin;}
        if($valMax!==''){$where.=' AND l.valor<=:max';$params[':max']=(float)$valMax;}
        return compact('where','params');
    }
    public function buscarLeituras(string $where,array $params,bool $todas=false,int $pagina=1,int $limite=200): array
    {
        $sql="SELECT l.*,s.nome AS sensor_nome,s.tipo AS sensor_tipo,s.intervalo_comunicacao,e.nome AS estoque_nome FROM leitura_sensor l JOIN sensores s ON s.id=l.sensor_id JOIN estoques e ON e.id=l.estoque_id $where ORDER BY l.lido_em DESC,l.id DESC";
        if(!$todas)$sql.=' LIMIT '.(int)$limite.' OFFSET '.((max(1,$pagina)-1)*$limite);
        $st=$this->pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
        foreach($rows as &$r){$r['valor']=(float)$r['valor'];$r['unidade']=SensorModel::unidade($r['sensor_tipo']);}
        return $rows;
    }
    public function buscarResumo(string $where,array $params): array
    {
        $st=$this->pdo->prepare("SELECT s.tipo AS sensor_tipo,COUNT(*) AS total,ROUND(AVG(l.valor),2) AS media,MIN(l.valor) AS minimo,MAX(l.valor) AS maximo,SUM(l.situacao<>'normal') AS fora_limites FROM leitura_sensor l JOIN sensores s ON s.id=l.sensor_id $where GROUP BY s.tipo ORDER BY s.tipo");$st->execute($params);$rows=$st->fetchAll();foreach($rows as &$row){foreach(['total','fora_limites'] as $k)$row[$k]=(int)$row[$k];foreach(['media','minimo','maximo'] as $k)$row[$k]=(float)$row[$k];}return $rows;
    }
    public function consultar(array $f,bool $todas=false): array
    {
        $m=$this->montarFiltros($f['data_ini'],$f['data_fim'],$f['sensor'],$f['val_min'],$f['val_max'],$f['estoque_id'],$f['sensor_id'],$f['horas']??0);
        $leituras=$this->buscarLeituras($m['where'],$m['params'],$todas,$f['pagina'],$f['por_pagina']??200);
        $resumo=$this->buscarResumo($m['where'],$m['params']);
        $where=str_replace(['l.lido_em','l.estoque_id','l.sensor_id','l.valor'],['a.criado_em','a.estoque_id','a.sensor_id','a.valor'],$m['where']);
        $st=$this->pdo->prepare("SELECT a.*,s.tipo AS sensor_tipo,s.nome AS sensor_nome,e.nome AS estoque_nome FROM alertas a JOIN sensores s ON s.id=a.sensor_id JOIN estoques e ON e.id=a.estoque_id $where ORDER BY a.criado_em DESC,a.id DESC".($todas?'':' LIMIT 25'));$st->execute($m['params']);$alertas=$st->fetchAll();
        $st=$this->pdo->prepare("SELECT COUNT(*) FROM alertas a JOIN sensores s ON s.id=a.sensor_id $where");$st->execute($m['params']);$totalAlertas=(int)$st->fetchColumn();
        // Somente intervalos com duas amostras consecutivas e sem lacuna de comunicação
        // sustentam uma estimativa de tempo fora dos limites. Nunca inferir além da última leitura.
        $periodos=[];$anterior=[];
        $wherePeriodo=$m['where'];$paramsPeriodo=$m['params'];$segundosFora=0;$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
        $st=$this->pdo->prepare("SELECT l.id,l.sensor_id,l.estoque_id,l.lido_em,l.situacao,s.nome AS sensor_nome,s.intervalo_comunicacao FROM leitura_sensor l JOIN sensores s ON s.id=l.sensor_id $wherePeriodo ORDER BY l.sensor_id,l.estoque_id,l.lido_em,l.id");$st->execute($paramsPeriodo);
        while($l=$st->fetch()){
            $key=$l['sensor_id'].':'.$l['estoque_id'];$prev=$anterior[$key]??null;
            if($prev && $prev['situacao']!=='normal'){
                $delta=strtotime($l['lido_em'])-strtotime($prev['lido_em']);
                if($delta>0&&$delta<=(int)$l['intervalo_comunicacao']){ $segundosFora+=$delta;if($todas||defined('API_CONTEXT'))$periodos[]=['sensor_id'=>$l['sensor_id'],'sensor_nome'=>$l['sensor_nome'],'inicio'=>$prev['lido_em'],'fim'=>$l['lido_em'],'segundos'=>$delta,'situacao'=>$prev['situacao']]; }
            }
            $anterior[$key]=$l;
        }
        $st->closeCursor();$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
        // Com filtro por valor, amostras intermediárias podem ter sido excluídas: não estimar duração.
        if($f['val_min']!==''||$f['val_max']!==''){$periodos=[];$segundosFora=0;}
        return compact('leituras','resumo','alertas','periodos')+['total_alertas'=>$totalAlertas,'total'=>(int)array_sum(array_column($resumo,'total')),'segundos_fora_limites'=>$segundosFora];
    }
}
