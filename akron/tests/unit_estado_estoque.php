<?php
/** Teste unitário sem banco: php tests/unit_estado_estoque.php */
define('AKRON_ACESSO_BANCO_LEGADO',true);
require __DIR__.'/../app/models/EstoqueModel.php';
function s($nome,$com,$sit,$st='ativo',$v=20.0){return ['nome'=>$nome,'status'=>$st,'comunicacao'=>$com,'situacao_ambiental'=>$sit,'ultimo_valor'=>$v];}
$casos=[
 'parcial: 2 online normais + 1 sem comunicação'=>[[s('A','online','normal'),s('B','online','normal'),s('C','sem_comunicacao','normal')],'ativo',3],
 'crítico online + sem comunicação'=>[[s('A','online','critico'),s('C','sem_comunicacao','normal')],'ativo',2],
 'sensor MUDO com última leitura crítica não conta'=>[[s('A','online','normal'),s('C','sem_comunicacao','critico')],'ativo',2],
 'todos sem comunicação'=>[[s('A','sem_comunicacao','normal'),s('B','sem_comunicacao','normal')],'ativo',2],
 'sem sensores'=>[[],'ativo',0],
 'só sensores inativos'=>[[s('A','inativo','normal','inativo')],'ativo',1],
 'atenção'=>[[s('A','online','atencao'),s('B','online','normal')],'ativo',2],
 'online mas sem leitura (valor nulo)'=>[[s('A','online','normal','ativo',null)],'ativo',1],
 'estoque inativo'=>[[s('A','inativo','normal')],'inativo',1],
];
foreach($casos as $n=>$c){$r=EstoqueModel::classificar(...$c);printf("%-52s amb=%-12s com=%-20s cad=%-7s onl=%d mudos=%d\n",$n,$r['condicao_ambiental'],$r['comunicacao_rotulo'],$r['situacao_cadastral'],$r['sensores_online'],$r['sensores_sem_comunicacao']);}
