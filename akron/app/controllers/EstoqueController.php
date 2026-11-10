<?php
require_once __DIR__ . '/../models/EstoqueModel.php';
class EstoqueController
{
    public function __construct(private PDO $pdo) {}
    public function executar(string $acao, array $dados): array
    {
        if (!ehAdmin()) return ['mensagem'=>'','erros'=>['Acesso negado.']];
        if (!in_array($acao,['cadastrar','atualizar','desativar'],true)) return ['mensagem'=>'','erros'=>['Ação desconhecida.']];
        $model=new EstoqueModel($this->pdo);
        $id=filter_var($dados['id'] ?? $dados['estoque_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($acao!='cadastrar' && (!$id || !$model->buscarPorId($id))) return ['mensagem'=>'','erros'=>['Estoque não encontrado.']];
        if ($acao==='desativar') {
            if (($dados['confirmacao']??'')!=='desativar') return ['mensagem'=>'','erros'=>['Confirme a desativação.']];
            $dados=$model->buscarPorId($id); $dados['status']='inativo';
        }
        $campos=[]; $erros=[];
        foreach (['nome'=>100,'descricao'=>2000,'localizacao'=>150] as $campo=>$max) {
            if (!is_scalar($dados[$campo]??'')) { $erros[]='Campo inválido: '.$campo; continue; }
            $campos[$campo]=trim((string)($dados[$campo]??''));
            if ((function_exists('mb_strlen') ? mb_strlen($campos[$campo]) : strlen($campos[$campo]))>$max) $erros[]="O campo $campo excede $max caracteres.";
        }
        if (strlen($campos['nome']??'')<3) $erros[]='Informe um nome com pelo menos 3 caracteres.';
        $campos['status']=$dados['status']??'ativo';
        if (!in_array($campos['status'],['ativo','inativo'],true)) $erros[]='Status inválido.';
        if ($erros) return ['mensagem'=>'','erros'=>$erros];
        try {
            $id=$model->salvar($campos,$acao==='cadastrar'?null:$id);
            registrarLog($this->pdo,(int)$_SESSION['usuario_id'],'ESTOQUE_'.strtoupper($acao),'Estoque '.$id.': '.json_encode($campos,JSON_UNESCAPED_UNICODE));
            return ['mensagem'=>$acao==='desativar'?'Estoque desativado. Histórico preservado.':'Estoque salvo com sucesso.','erros'=>[],'id'=>$id];
        } catch (PDOException $e) { error_log($e->getMessage()); return ['mensagem'=>'','erros'=>['Não foi possível salvar o estoque.']]; }
    }
    public function processarRequisicao(): array
    {
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') return ['mensagem'=>'','erros'=>[]];
        if (!validarCSRF($_POST['csrf_token']??'')) return ['mensagem'=>'','erros'=>['Token de segurança inválido.']];
        return $this->executar($_POST['acao']??'',$_POST);
    }
}
