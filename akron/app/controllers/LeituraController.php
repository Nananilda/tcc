<?php
require_once __DIR__ . '/../models/AlertaModel.php';
require_once __DIR__ . '/../models/LeituraModel.php';

/** Entrada única de leituras: validação, instantâneo histórico e alertas na mesma transação. */
class LeituraController
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /** $origem vem do canal autenticado (gateway com chave = 'sensor'; administrador = 'manual'), nunca do corpo da requisição. */
    public function registrar(array $dados, ?int $usuarioId = null, string $origem = 'manual'): array
    {
        $sensorId = filter_var($dados['sensor_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $valor = lerNumero($dados['valor'] ?? null);
        if ($sensorId === false || $valor === null) return ['mensagem'=>'','erros'=>['Informe um sensor válido e um valor numérico (ex.: 36,5).']];
        try {
            $this->pdo->beginTransaction();
            // O lock serializa ingestões deste sensor. Permissões da porta de entrada já foram verificadas.
            $q=$this->pdo->prepare('SELECT s.*,e.status AS estoque_status FROM sensores s JOIN estoques e ON e.id=s.estoque_id WHERE s.id=:id FOR UPDATE');
            $q->execute([':id'=>$sensorId]); $sensor=$q->fetch();
            if (!$sensor || $sensor['status'] !== 'ativo' || $sensor['estoque_status'] !== 'ativo') {
                $this->pdo->rollBack();
                return ['mensagem'=>'','erros'=>['Sensor não encontrado ou sensor/estoque inativo.']];
            }
            $violacao=AlertaModel::avaliarLimite($sensor,$valor);
            $params=[':sensor'=>$sensorId,':estoque'=>$sensor['estoque_id'],':valor'=>$valor,':situacao'=>$violacao['severidade'] ?? 'normal'];
            foreach (['atencao_min','atencao_max','critico_min','critico_max'] as $campo) $params[':'.$campo]=$sensor['limite_'.$campo] ?? null;
            $q=$this->pdo->prepare('INSERT INTO leitura_sensor(sensor_id,estoque_id,valor,lido_em,situacao,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max,origem) VALUES(:sensor,:estoque,:valor,NOW(),:situacao,:atencao_min,:atencao_max,:critico_min,:critico_max,:origem)');
            $params[':origem']=isset(LeituraModel::ORIGENS[$origem])&&$origem!=='nao_informada'?$origem:'manual';
            $q->execute($params); $leituraId=(int)$this->pdo->lastInsertId();
            $q=$this->pdo->prepare('UPDATE sensores SET ultima_comunicacao=NOW() WHERE id=:id'); $q->execute([':id'=>$sensorId]);
            $alerta=$this->atualizarOcorrencia($sensor,$valor,$violacao);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('['.APP_NOME.'] Erro ao registrar leitura: '.$e->getMessage());
            return ['mensagem'=>'','erros'=>['Não foi possível registrar a leitura. Tente novamente.']];
        }
        if ($usuarioId !== null && function_exists('registrarLog')) registrarLog($this->pdo,$usuarioId,'LEITURA_MANUAL',"Sensor $sensorId, leitura $leituraId");
        return ['mensagem'=>$alerta === null ? 'Leitura registrada. Condição ambiental normal.' : ($alerta['novo'] ? 'Leitura registrada e alerta gerado.' : 'Leitura registrada e ocorrência contínua atualizada.'),
            'erros'=>[],'leitura_id'=>$leituraId,'estoque_id'=>(int)$sensor['estoque_id'],'situacao'=>$violacao['severidade'] ?? 'normal','alerta'=>$alerta];
    }

    private function atualizarOcorrencia(array $sensor,float $valor,?array $violacao): ?array
    {
        $id=(int)$sensor['id'];
        if ($violacao === null) {
            // Normalizar não encerra atendimento: os dois momentos ficam registrados separadamente.
            $q=$this->pdo->prepare("UPDATE alertas SET normalizado_em=NOW(),status=IF(resolvido=1,'resolvido','normalizado') WHERE sensor_id=:sensor AND estoque_id=:estoque AND automatico=1 AND normalizado_em IS NULL");
            $q->execute([':sensor'=>$id,':estoque'=>$sensor['estoque_id']]); return null;
        }
        // Mesmo após resolução administrativa, não repetir a ocorrência até haver normalização real.
        $q=$this->pdo->prepare('SELECT id,severidade FROM alertas WHERE sensor_id=:sensor AND estoque_id=:estoque AND automatico=1 AND normalizado_em IS NULL ORDER BY id DESC LIMIT 1');
        $q->execute([':sensor'=>$id,':estoque'=>$sensor['estoque_id']]); $existente=$q->fetch();
        if ($existente) {
            // Mantém o maior grau observado durante a ocorrência e o respectivo valor/limite.
            if ($existente['severidade'] !== 'critico' || $violacao['severidade'] === 'critico') {
                $q=$this->pdo->prepare('UPDATE alertas SET severidade=:grau,mensagem=:mensagem,valor=:valor,limite_ultrapassado=:limite WHERE id=:id');
                $q->execute([':grau'=>$violacao['severidade'],':mensagem'=>$violacao['mensagem'],':valor'=>$valor,':limite'=>$violacao['limite_ultrapassado'],':id'=>$existente['id']]);
            }
            return $violacao+['id'=>(int)$existente['id'],'novo'=>false];
        }
        $q=$this->pdo->prepare('INSERT INTO alertas(sensor_id,estoque_id,severidade,mensagem,valor,limite_ultrapassado,automatico) VALUES(:sensor,:estoque,:grau,:mensagem,:valor,:limite,1)');
        $q->execute([':sensor'=>$id,':estoque'=>$sensor['estoque_id'],':grau'=>$violacao['severidade'],':mensagem'=>$violacao['mensagem'],':valor'=>$valor,':limite'=>$violacao['limite_ultrapassado']]);
        return $violacao+['id'=>(int)$this->pdo->lastInsertId(),'novo'=>true];
    }
}
