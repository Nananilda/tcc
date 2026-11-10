<?php
require_once __DIR__.'/../models/RelatorioModel.php';
require_once __DIR__.'/../models/AlertaModel.php';
/** Mesmos filtros e permissões no primeiro carregamento e nas próximas páginas. */
final class HistoricoEntidade
{
    public function __construct(private PDO $pdo) {}
    public function filtros(array $entrada): array
    {
        return (new RelatorioModel($this->pdo))->filtrosDaRequisicao(array_replace(['data_ini'=>'1900-01-01','data_fim'=>date('Y-m-d')],array_filter($entrada,fn($v)=>$v!=='')));
    }
    public function consultar(array $f, string $tipo, int $offset=0, int $limite=10): array
    {
        if ($tipo==='alertas') {
            $af=array_filter(['estoque_id'=>$f['estoque_id'],'sensor_id'=>$f['sensor_id'],'data_inicial'=>$f['data_ini'],'data_final'=>$f['data_fim']]);
            $m=new AlertaModel($this->pdo);
            return ['registros'=>$m->listarRecentes($limite,$af,1,false,true,$offset),'total'=>$m->contarTodos($af)];
        }
        $m=new RelatorioModel($this->pdo);$q=$m->montarFiltros($f['data_ini'],$f['data_fim'],$f['sensor'],'','',$f['estoque_id'],$f['sensor_id']);
        $where=$q['where'];
        $st=$this->pdo->prepare("SELECT COUNT(*) FROM leitura_sensor l JOIN sensores s ON s.id=l.sensor_id $where");$st->execute($q['params']);$total=(int)$st->fetchColumn();
        $st=$this->pdo->prepare("SELECT l.*,s.nome AS sensor_nome,s.tipo AS sensor_tipo,e.nome AS estoque_nome FROM leitura_sensor l JOIN sensores s ON s.id=l.sensor_id JOIN estoques e ON e.id=l.estoque_id $where ORDER BY l.lido_em DESC,l.id DESC LIMIT ".(int)$limite.' OFFSET '.(int)$offset);$st->execute($q['params']);
        return ['registros'=>$st->fetchAll(),'total'=>$total];
    }
}
