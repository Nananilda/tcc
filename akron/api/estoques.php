<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../app/controllers/EstoqueController.php';
exigirLoginApi();
if ($_SERVER['REQUEST_METHOD']==='GET') {
    $model=new EstoqueModel($pdo);
    if (isset($_GET['id'])) {
        $id=filter_var($_GET['id'],FILTER_VALIDATE_INT);
        if (!$id || $id<1) jsonResponse(['sucesso'=>false,'mensagem'=>'ID inválido.'],422);
        exigirEstoque($pdo,$id);
        jsonResponse(['sucesso'=>true,'estoque'=>$model->buscarPorId($id)]);
    }
    jsonResponse(['sucesso'=>true,'estoques'=>$model->listarTodos()]);
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    exigirAdminApi(); $dados=corpoRequisicao();
    respostaDoControlador((new EstoqueController($pdo))->executar($dados['acao']??'cadastrar',$dados));
}
jsonResponse(['sucesso'=>false,'mensagem'=>'Método não permitido.'],405);
