<?php
require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();
require_once __DIR__ . '/../app/controllers/AlertaController.php';
$controller=new AlertaController($pdo,ehAdmin());
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $filtros=AlertaModel::validarFiltros($_GET);
        $pagina=max(1,(int)($_GET['pagina'] ?? 1));
        $alertas=$controller->listarAlertas($filtros,$pagina);
        foreach ($alertas as &$a) {
            foreach (['id','sensor_id','estoque_id'] as $k) $a[$k]=(int)$a[$k];
            $a['resolvido']=(bool)$a['resolvido']; $a['automatico']=(bool)$a['automatico'];
            foreach (['valor','limite_ultrapassado'] as $k) $a[$k]=$a[$k]===null ? null : (float)$a[$k];
        }
        unset($a);
        jsonResponse(['sucesso'=>true,'alertas'=>$alertas,'contagem'=>$controller->contarPorSeveridade($filtros),
            'total'=>$controller->contarTodos($filtros),'pagina'=>$pagina,'por_pagina'=>50,
            'limites'=>AlertaModel::LIMITES,'limites_observacao'=>'Valores iniciais; consulte os limites individuais em sensores.']);
    } catch (InvalidArgumentException $e) { jsonResponse(['sucesso'=>false,'mensagem'=>$e->getMessage()],422);
    } catch (PDOException $e) { error_log($e->getMessage()); jsonResponse(['sucesso'=>false,'mensagem'=>'Não foi possível carregar os alertas.'],500); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdminApi(); $dados=corpoRequisicao();
    respostaDoControlador($controller->executar(is_string($dados['acao'] ?? null) ? $dados['acao'] : '',$dados));
}
jsonResponse(['sucesso'=>false,'mensagem'=>'Método não permitido.'],405);
