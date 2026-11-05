<?php
require_once __DIR__.'/../models/LeituraModel.php';
require_once __DIR__.'/RelatorioPdf.php';
function escreverCsv($saida,array $campos): void
{
    foreach($campos as &$c)if(is_string($c)&&preg_match('/^[=+@\-\t\r]/',$c))$c="'".$c;
    unset($c);fputcsv($saida,$campos,';','"','');
}
function exportarDetalhes(array $r,string $formato,string $nome): never
{
    header('Cache-Control: no-store');header('Content-Disposition: attachment; filename="akron-'.$nome.'.'.$formato.'"');
    if($formato==='pdf'){header('Content-Type: application/pdf');echo RelatorioPdf::gerar($r);exit;}
    header('Content-Type: text/csv; charset=utf-8');$s=fopen('php://output','w');fwrite($s,"\xEF\xBB\xBF");
    if($nome!=='alertas'&&!empty($r['identificacao'])){escreverCsv($s,['IDENTIFICAÇÃO']);foreach($r['identificacao'] as $campo=>$v)escreverCsv($s,[$campo,$v]);}
    if(!empty($r['sensores'])){
        escreverCsv($s,['SENSORES VINCULADOS']);escreverCsv($s,['ID','Nome','Tipo','Estoque','Localização','Situação cadastral','Atenção inferior','Atenção superior','Crítico inferior','Crítico superior','Prazo de comunicação (s)']);
        foreach($r['sensores'] as $v)escreverCsv($s,[$v['id'],$v['nome'],SensorModel::rotuloComUnidade($v['tipo']),$v['estoque_nome'],$v['localizacao'],$v['status'],$v['limite_atencao_min'],$v['limite_atencao_max'],$v['limite_critico_min'],$v['limite_critico_max'],$v['intervalo_comunicacao']]);
    }
    if($nome!=='alertas'){
        escreverCsv($s,['RESUMO']);escreverCsv($s,['Tipo','Leituras','Média','Mínimo','Máximo','Leituras fora dos limites']);
        foreach($r['resumo']??[] as $v)escreverCsv($s,[SensorModel::rotuloComUnidade($v['sensor_tipo']),$v['total'],$v['media'],$v['minimo'],$v['maximo'],$v['fora_limites']]);
        escreverCsv($s,['HISTÓRICO DE LEITURAS']);escreverCsv($s,['Data e horário','Estoque','Sensor','Tipo','Valor','Unidade','Estado ambiental','Origem do dado']);
        foreach($r['leituras']??[] as $l)escreverCsv($s,[dataBr($l['lido_em']),$l['estoque_nome'],$l['sensor_nome'],SensorModel::rotulo($l['sensor_tipo']),$l['valor'],$l['unidade'],['normal'=>'Normal','atencao'=>'Atenção','critico'=>'Crítico'][$l['situacao']],LeituraModel::rotuloOrigem($l['origem']??null)]);
    }
    if($nome!=='alertas')escreverCsv($s,['ALERTAS']);
    escreverCsv($s,['ID','Gravidade','Mensagem','Estoque','Sensor','Valor registrado','Unidade','Limite ultrapassado','Data e horário','Situação','Reconhecido por','Reconhecido em','Normalizado em','Resolvido por','Resolvido em','Observações']);
    foreach($r['alertas'] as $a)escreverCsv($s,[$a['id'],AlertaModel::rotuloSeveridade($a['severidade']),$a['mensagem'],$a['estoque_nome'],$a['sensor_nome'],$a['valor'],SensorModel::unidade($a['sensor_tipo']??''),$a['limite_ultrapassado'],dataBr($a['criado_em']),AlertaModel::STATUS[$a['status']],$a['reconhecido_nome']??$a['reconhecido_por'],dataBr($a['reconhecido_em']),dataBr($a['normalizado_em']),$a['resolvido_nome']??$a['resolvido_por'],dataBr($a['resolvido_em']),$a['observacoes']]);
    fclose($s);exit;
}
