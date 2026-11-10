<?php
require_once __DIR__.'/../models/RelatorioModel.php';
class RelatorioController
{
    private RelatorioModel $model;
    public function __construct(PDO $pdo){$this->model=new RelatorioModel($pdo);}
    public function consultar(array $entrada,bool $todas=false): array
    {
        $r=['filtros'=>[],'leituras'=>[],'resumo'=>[],'alertas'=>[],'periodos'=>[],'total'=>0,'segundos_fora_limites'=>0,'erro'=>''];
        try{$r['filtros']=$this->model->filtrosDaRequisicao($entrada);$r=array_replace($r,$this->model->consultar($r['filtros'],$todas));}
        catch(InvalidArgumentException $e){$r['erro']=$e->getMessage();}
        catch(PDOException $e){error_log($e->getMessage());$r['erro']='Não foi possível consultar o relatório. Tente novamente.';}
        return $r;
    }
    public function rotulosSensores(): array{return $this->model->getLabels();}
}
