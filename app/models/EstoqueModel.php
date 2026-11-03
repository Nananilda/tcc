<?php
require_once __DIR__ . '/../../includes/estoques.php';
class EstoqueModel
{
    public function __construct(private PDO $pdo) {}
    public function listarTodos(): array
    {
        $sql = "SELECT e.*, (SELECT COUNT(*) FROM sensores s WHERE s.estoque_id=e.id) AS sensores_total,
          (SELECT COUNT(*) FROM alertas a WHERE a.estoque_id=e.id AND a.status IN ('pendente','reconhecido')) AS alertas_ativos,
          (SELECT COUNT(*) FROM sensores s WHERE s.estoque_id=e.id AND s.status='ativo' AND s.ultima_comunicacao >= DATE_SUB(NOW(), INTERVAL s.intervalo_comunicacao SECOND)) AS sensores_online,
          (SELECT MAX(a.severidade='critico') FROM alertas a WHERE a.estoque_id=e.id AND a.resolvido=0 AND a.status!='normalizado') AS critico,
          (SELECT COUNT(*) FROM alertas a WHERE a.estoque_id=e.id AND a.resolvido=0 AND a.status!='normalizado' AND a.severidade='atencao') AS atencao
          FROM estoques e WHERE " . escopoEstoque('e.id') . ' ORDER BY e.status, e.nome';
        $rows = $this->pdo->query($sql)->fetchAll();
        require_once __DIR__.'/LeituraModel.php';
        $atuais=(new LeituraModel($this->pdo))->atuais();
        $porEstoque=[];foreach($atuais as $sensor)$porEstoque[(int)$sensor['estoque_id']][]=$sensor;
        foreach ($rows as &$row) {
            if($row['status']==='inativo')$row['sensores_online']=0;
            $condicao='Sem comunicação';$critico=false;$atencao=false;$sem=false;$online=false;
            foreach ($porEstoque[(int)$row['id']]??[] as $sensor) if ($sensor['status']==='ativo') {
                $online=$online||$sensor['comunicacao']==='online'; $sem=$sem||$sensor['comunicacao']!=='online';
                if ($sensor['comunicacao']==='online') { $critico=$critico||$sensor['situacao_ambiental']==='critico'; $atencao=$atencao||$sensor['situacao_ambiental']==='atencao'; }
            }
            $row['condicao']=$row['status']==='inativo'?'Inativo':(!(int)$row['sensores_total']?'Sem sensores':($critico?'Crítico':($atencao?'Atenção':($sem?'Sem comunicação':($online?'Normal':'Sem comunicação')))));
        }
        return $rows;
    }
    public function buscarPorId(int $id): array|false
    {
        $stmt=$this->pdo->prepare('SELECT e.* FROM estoques e WHERE e.id=? AND '.escopoEstoque('e.id'));
        $stmt->execute([$id]); return $stmt->fetch();
    }
    public function salvar(array $dados, ?int $id = null): int
    {
        if ($id) {
            $stmt=$this->pdo->prepare('UPDATE estoques SET nome=?,descricao=?,localizacao=?,status=? WHERE id=?');
            $stmt->execute([$dados['nome'],$dados['descricao'],$dados['localizacao'],$dados['status'],$id]);
        } else {
            $stmt=$this->pdo->prepare('INSERT INTO estoques (nome,descricao,localizacao,status) VALUES (?,?,?,?)');
            $stmt->execute([$dados['nome'],$dados['descricao'],$dados['localizacao'],$dados['status']]);
            $id=(int)$this->pdo->lastInsertId();
        }
        return $id;
    }
}
