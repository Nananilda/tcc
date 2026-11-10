<?php
require_once __DIR__.'/../models/LeituraModel.php';
require_once __DIR__.'/../models/RelatorioModel.php';
class GraficoController
{
    private PDO $pdo;
    public function __construct(PDO $pdo){$this->pdo=$pdo;}
    public function serie(array $entrada): array
    {
        $tipoEntrada=$entrada['sensor']??$entrada['tipo']??null;
        $sensor=LeituraModel::normalizarSensor(is_string($tipoEntrada)?$tipoEntrada:null);
        $horas=LeituraModel::normalizarHoras($entrada['horas']??24);
        $resultado=['sensor'=>$sensor,'horas'=>$horas,'pontos'=>[],'total'=>0,'atuais'=>[],'lacunas'=>[],'sensores_desatualizados'=>[],'janela'=>null,'erro'=>''];
        try{
            $filtros=(new RelatorioModel($this->pdo))->filtrosDaRequisicao($entrada);
            if(empty($entrada['data_ini']) && empty($entrada['data_fim'])) unset($filtros['data_ini'],$filtros['data_fim']);
            $model=new LeituraModel($this->pdo);
            $resultado['pontos']=$model->serie($sensor,$horas,$filtros);
            $resultado['atuais']=array_values(array_filter($model->atuais($filtros['estoque_id'],$filtros['sensor_id']),fn($s)=>$s['tipo']===$sensor));
            $resultado['total']=(int)array_sum(array_column($resultado['pontos'],'amostras'));
            // Janela exibida e lacunas: o gráfico não pode sugerir continuidade onde não houve leituras.
            $agora=time();
            if(!empty($filtros['data_ini'])&&!empty($filtros['data_fim'])){
                $inicio=strtotime($filtros['data_ini'].' 00:00:00');$fim=min(strtotime($filtros['data_fim'].' 23:59:59'),$agora);
                if($filtros['data_ini']<'2000-01-01'&&$resultado['pontos'])$inicio=strtotime($resultado['pontos'][0]['inicio_intervalo']);
            } else {$fim=$agora;$inicio=$agora-$horas*3600;}
            $analise=LeituraModel::detectarLacunas($resultado['pontos'],$fim,$agora);
            $resultado['pontos']=$analise['pontos'];$resultado['lacunas']=$analise['lacunas'];$resultado['sensores_desatualizados']=$analise['sensores_desatualizados'];
            $resultado['janela']=['inicio'=>date('Y-m-d H:i:s',$inicio),'fim'=>date('Y-m-d H:i:s',$fim)];
        }catch(InvalidArgumentException $e){$resultado['erro']=$e->getMessage();}
        catch(PDOException $e){error_log($e->getMessage());$resultado['erro']='Não foi possível carregar as leituras.';}
        return $resultado;
    }
    public function conjunto(array $entrada): array
    {
        $r=['sucesso'=>true,'series'=>[],'atuais'=>[],'total'=>0,'erro'=>''];
        try {
            $f=(new RelatorioModel($this->pdo))->filtrosDaRequisicao($entrada);
            $r['atuais']=array_values(array_filter((new LeituraModel($this->pdo))->atuais($f['estoque_id'],$f['sensor_id']),fn($s)=>!$f['sensor']||$s['tipo']===$f['sensor']));
            foreach ($f['sensor']?[$f['sensor']]:array_keys(SensorModel::TIPOS) as $tipo) {
                $serie=$this->serieJson(array_replace($entrada,['sensor'=>$tipo]));
                if ($serie['erro']) throw new InvalidArgumentException($serie['erro']);
                if ($serie['total']) $r['series'][$tipo]=$serie;
                $r['total']+=$serie['total'];
            }
        } catch (InvalidArgumentException $e) {$r['erro']=$e->getMessage();$r['sucesso']=false;}
        return $r;
    }
    public function serieJson(array $entrada): array { $r=$this->serie($entrada);return $r+['sucesso'=>$r['erro']==='','labels'=>array_column($r['pontos'],'lido_em'),'valores'=>array_column($r['pontos'],'valor')]; }
}
