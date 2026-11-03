<?php
require_once __DIR__ . '/../models/AlertaModel.php';

class AlertaController
{
    private PDO $pdo;
    private AlertaModel $model;
    private bool $ehAdmin;
    public function __construct(PDO $pdo,bool $ehAdmin) { $this->pdo=$pdo; $this->model=new AlertaModel($pdo); $this->ehAdmin=$ehAdmin; }
    public function processarRequisicao(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return ['mensagem'=>'','erros'=>[]];
        if (!is_string($_POST['csrf_token'] ?? null) || !validarCSRF($_POST['csrf_token'])) return ['mensagem'=>'','erros'=>['Token de segurança inválido.']];
        return $this->executar(is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '',$_POST);
    }
    public function executar(string $acao,array $dados): array
    {
        if (!$this->ehAdmin) return ['mensagem'=>'','erros'=>['Acesso negado.']];
        try {
            if ($acao === 'criar') return $this->criar($dados);
            if (!in_array($acao,['reconhecer','resolver'],true)) throw new InvalidArgumentException('Ação desconhecida.');
            $id=filter_var($dados['alerta_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $alerta=$id ? $this->model->buscarPorId($id) : false;
            if (!$alerta) throw new InvalidArgumentException('Alerta não encontrado.');
            $usuario=(int)($_SESSION['usuario_id'] ?? 0);
            if ($acao === 'reconhecer') {
                if (!$this->model->reconhecer($id,$usuario)) throw new InvalidArgumentException('Somente alertas pendentes podem ser reconhecidos.');
                $mensagem='Alerta reconhecido.';
            } else {
                if (!is_string($dados['observacoes'] ?? '')) throw new InvalidArgumentException('Observações inválidas.');
                $observacoes=trim($dados['observacoes'] ?? '');
                if (strlen($observacoes)>5000) throw new InvalidArgumentException('Observações muito longas (máximo de 5000 bytes).');
                if (!$this->model->marcarResolvido($id,$usuario,$observacoes)) throw new InvalidArgumentException('Este alerta já foi resolvido.');
                $mensagem='Alerta resolvido. Responsável e horário registrados.';
            }
            registrarLog($this->pdo,$usuario,'ALERTA_'.strtoupper($acao),"Alerta $id");
            return ['mensagem'=>$mensagem,'erros'=>[]];
        } catch (InvalidArgumentException $e) { return ['mensagem'=>'','erros'=>[$e->getMessage()]];
        } catch (PDOException $e) { error_log('['.APP_NOME.'] Alertas: '.$e->getMessage()); return ['mensagem'=>'','erros'=>['Não foi possível atualizar o alerta. Tente novamente.']]; }
    }
    private function criar(array $dados): array
    {
        $id=filter_var($dados['sensor_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $grau=$dados['severidade'] ?? '';
        $mensagem=is_string($dados['mensagem'] ?? null) ? trim($dados['mensagem']) : '';
        $tamanho=function_exists('mb_strlen') ? mb_strlen($mensagem) : strlen($mensagem);
        if (!$id || !is_string($grau) || !AlertaModel::severidadeValida($grau) || $tamanho<3 || $tamanho>200) throw new InvalidArgumentException('Informe sensor, gravidade e mensagem de 3 a 200 caracteres.');
        $valor=null;
        if (($dados['valor'] ?? '') !== '' && ($dados['valor'] ?? null) !== null) {
            $valor=lerNumero($dados['valor']);
            if ($valor === null) throw new InvalidArgumentException('Valor medido inválido.');
        }
        $sensor=(new SensorModel($this->pdo))->buscarPorId($id);
        if (!$sensor) throw new InvalidArgumentException('Sensor não encontrado.');
        $alertaId=$this->model->criar($id,$grau,$mensagem,$valor);
        registrarLog($this->pdo,$_SESSION['usuario_id'] ?? null,'ALERTA_CRIADO',"Alerta $alertaId, sensor $id");
        return ['mensagem'=>'Alerta criado com sucesso.','erros'=>[],'id'=>$alertaId];
    }
    public function listarAlertas(array $filtros=[],int $pagina=1): array { return $this->model->listarRecentes(50,$filtros,$pagina); }
    public function contarPorSeveridade(array $filtros=[]): array { return $this->model->contarPorSeveridade($filtros); }
    public function contarTodos(array $filtros=[]): int { return $this->model->contarTodos($filtros); }
}
