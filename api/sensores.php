<?php
/**
 * api/sensores.php
 *
 * GET  -> lista todos os sensores (qualquer usuário logado — usado tanto
 *         pela consulta somente-leitura quanto pela gestão).
 * POST -> acao=cadastrar | acao=toggle_status (SOMENTE ADMIN).
 *         Usuário comum não tem acesso a estas ações: a Gestão de Sensores
 *         é restrita a administradores, tanto aqui quanto na tela do app.
 *
 * Body POST (JSON):
 *   cadastrar:      { "acao": "cadastrar", "nome", "tipo", "localizacao", "status" }
 *   toggle_status:  { "acao": "toggle_status", "sensor_id", "novo_status" }
 */

require_once __DIR__ . '/_bootstrap.php';
exigirLoginApi();

require_once __DIR__ . '/../app/controllers/SensorController.php';

$ehAdmin    = ehAdmin();
$controller = new SensorController($pdo, $ehAdmin);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $erros    = [];
    $sensores = $controller->listarSensores($erros);
    jsonResponse(['sucesso' => true, 'sensores' => $sensores, 'erros' => $erros]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reforço explícito: gestão de sensores (cadastro/alteração de status)
    // é exclusiva de administradores — usuário comum é bloqueado aqui.
    exigirAdminApi();

    $dados = corpoRequisicao();
    $acao  = $dados['acao'] ?? '';

    $mensagem = '';
    $erros    = [];

    if ($acao === 'cadastrar') {
        $nome        = trim($dados['nome'] ?? '');
        $tipo        = trim($dados['tipo'] ?? '');
        $localizacao = trim($dados['localizacao'] ?? '');
        $status      = $dados['status'] ?? 'ativo';

        if (strlen($nome) < 3) {
            $erros[] = 'Nome do sensor deve ter ao menos 3 caracteres.';
        }
        if (!array_key_exists($tipo, SensorController::TIPOS_VALIDOS)) {
            $erros[] = 'Tipo de sensor inválido.';
        }
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $erros[] = 'Status inválido.';
        }

        if (empty($erros)) {
            try {
                require_once __DIR__ . '/../app/models/Sensor.php';
                $model = new SensorModel($pdo);
                $id = $model->cadastrar([
                    'nome'        => $nome,
                    'tipo'        => $tipo,
                    'localizacao' => $localizacao ?: null,
                    'status'      => $status,
                ]);
                registrarLog($pdo, $_SESSION['usuario_id'], 'SENSOR_CADASTRO', "Sensor ID $id: $nome ($tipo)");
                $mensagem = "Sensor \"$nome\" cadastrado com sucesso.";
            } catch (PDOException $e) {
                $erros[] = 'Erro ao salvar sensor: ' . $e->getMessage();
            }
        }
    } elseif ($acao === 'toggle_status') {
        $sensorId   = (int) ($dados['sensor_id'] ?? 0);
        $novoStatus = $dados['novo_status'] ?? '';

        if ($sensorId <= 0) {
            $erros[] = 'ID de sensor inválido.';
        } elseif (!in_array($novoStatus, ['ativo', 'inativo'], true)) {
            $erros[] = 'Status inválido.';
        } else {
            try {
                require_once __DIR__ . '/../app/models/Sensor.php';
                $model  = new SensorModel($pdo);
                $sensor = $model->buscarPorId($sensorId);
                if (!$sensor) {
                    $erros[] = 'Sensor não encontrado.';
                } else {
                    $model->atualizarStatus($sensorId, $novoStatus);
                    registrarLog($pdo, $_SESSION['usuario_id'], 'SENSOR_STATUS', "Sensor ID $sensorId → $novoStatus");
                    $mensagem = "Status do sensor atualizado para \"$novoStatus\".";
                }
            } catch (PDOException $e) {
                $erros[] = 'Erro ao alterar status: ' . $e->getMessage();
            }
        }
    } else {
        $erros[] = 'Ação desconhecida.';
    }

    jsonResponse(['sucesso' => empty($erros), 'mensagem' => $mensagem, 'erros' => $erros]);
}

jsonResponse(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
