<?php
require_once __DIR__.'/../models/LeituraModel.php';
require_once __DIR__.'/../models/EstoqueModel.php';
class DashboardController
{
    private PDO $pdo;
    public function __construct(PDO $pdo){$this->pdo=$pdo;}
    public function obterResumo(array $entrada=[]): array
    {
        $r=['estoques_total'=>0,'sensores_online'=>0,'sensores_ativos'=>0,'sensores_total'=>0,'alertas_pendentes'=>0,'estoques_irregulares'=>0,'estoques_comunicacao'=>0,'ultima_atualizacao'=>null,'atuais'=>[],'alertas_recentes'=>[],'alertas_periodo'=>[],'erro'=>''];
        try{
            $id=filter_var($entrada['estoque_id']??0,FILTER_VALIDATE_INT);
            if($id===false||$id<0)throw new InvalidArgumentException('Estoque inválido.');
            if($id&&!podeAcessarEstoque($this->pdo,$id))throw new InvalidArgumentException('Estoque indisponível ou sem permissão.');
            $r['atuais']=(new LeituraModel($this->pdo))->atuais($id);
            $r['sensores_total']=count($r['atuais']);
            foreach($r['atuais'] as $s){
                if($s['status']==='ativo')$r['sensores_ativos']++;
                if($s['comunicacao']==='online')$r['sensores_online']++;
                if($s['lido_em']!==null&&($r['ultima_atualizacao']===null||$s['lido_em']>$r['ultima_atualizacao']))$r['ultima_atualizacao']=$s['lido_em'];
            }
            $where=escopoEstoque('e.id').($id?' AND e.id='.(int)$id:'');
            $r['estoques_total']=(int)$this->pdo->query("SELECT COUNT(*) FROM estoques e WHERE $where")->fetchColumn();
            $where=escopoEstoque('a.estoque_id').($id?' AND a.estoque_id='.(int)$id:'');
            $n=$this->pdo->query("SELECT COUNT(*) AS total FROM alertas a WHERE $where AND a.status IN ('pendente','reconhecido')")->fetch();
            $r['alertas_pendentes']=(int)$n['total'];// Irregularidade ambiental e falha de comunicação são contadas separadamente.
            foreach ((new EstoqueModel($this->pdo))->listarTodos() as $e) {
                if ($id && (int)$e['id']!==$id) continue;
                if ($e['status']!=='ativo') continue;
                if (in_array($e['condicao_ambiental'],['atencao','critico'],true)) $r['estoques_irregulares']++;
                if (in_array($e['comunicacao_estado'],['parcial','sem_comunicacao'],true)) $r['estoques_comunicacao']++;
            }
            $r['alertas_recentes']=$this->pdo->query("SELECT a.*,s.nome AS sensor_nome,e.nome AS estoque_nome FROM alertas a JOIN sensores s ON s.id=a.sensor_id JOIN estoques e ON e.id=a.estoque_id WHERE $where AND a.status<>'resolvido' ORDER BY FIELD(a.status,'pendente','reconhecido','normalizado'),FIELD(a.severidade,'critico','atencao','info'),a.criado_em DESC LIMIT 6")->fetchAll();
            $r['alertas_periodo']=$this->pdo->query("SELECT DATE(a.criado_em) AS dia,COUNT(*) AS total FROM alertas a WHERE $where AND a.criado_em>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(a.criado_em) ORDER BY dia")->fetchAll();
        }catch(InvalidArgumentException $e){$r['erro']=$e->getMessage();}
        catch(PDOException $e){error_log($e->getMessage());$r['erro']='Não foi possível atualizar o painel.';}
        return $r;
    }
}
