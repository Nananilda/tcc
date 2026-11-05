<?php
/** Teste unitário sem banco: php tests/unit_lacunas.php */
define('AKRON_ACESSO_BANCO_LEGADO',true);
require __DIR__.'/../app/models/LeituraModel.php';
function p($t,$n=1,$i=300,$s=1){return ['sensor_id'=>$s,'estoque_id'=>1,'inicio_intervalo'=>$t,'lido_em'=>$t,'intervalo_comunicacao'=>$i,'amostras'=>$n];}
$agora=strtotime('2026-11-03 12:00:00');
$pts=[p('2026-11-03 08:00:00'),p('2026-11-03 08:05:00'),p('2026-11-03 08:10:30'),p('2026-11-03 09:30:00'),p('2026-11-03 09:35:00'),p('2026-11-03 11:58:00'),p('2026-11-03 07:00:00',1,300,2),p('2026-11-03 07:04:00',1,300,2),p('2026-11-03 07:08:00',1,300,2)];
usort($pts,fn($a,$b)=>strcmp($a['lido_em'],$b['lido_em']));
$r=LeituraModel::detectarLacunas($pts,$agora,$agora);
foreach($r['lacunas'] as $l)printf("sensor %d lacuna %s -> %s (%d s)%s\n",$l['sensor_id'],$l['de'],$l['ate'],$l['segundos'],$l['aberta']?' ABERTA':'');
echo 'desatualizados: '.json_encode($r['sensores_desatualizados'])."\n";
echo 'pontos com lacuna_antes: '.implode(',',array_map(fn($p)=>$p['lido_em'],array_filter($r['pontos'],fn($p)=>$p['lacuna_antes'])))."\n";
