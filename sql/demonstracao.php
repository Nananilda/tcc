<?php
/**
 * Dados de DEMONSTRAÇÃO do ÁKRON (apresentações e capturas de tela).
 *
 *   php sql/demonstracao.php instalar    cria estoques, sensores, leituras, alertas e usuários de demonstração
 *   php sql/demonstracao.php atualizar   desloca as datas para "agora", mantendo os sensores online na apresentação
 *   php sql/demonstracao.php remover     apaga SOMENTE os registros de demonstração
 *   php sql/demonstracao.php status      informa se há dados de demonstração
 *
 * Garantias:
 *  - Tudo é marcado: estoques.demonstracao=1, usuario.demonstracao=1 e leitura_sensor.origem='demonstracao'.
 *    Nenhum registro real é lido, alterado ou apagado.
 *  - Os valores são SIMULADOS. Não representam medições de sensores físicos e os limites usados aqui são
 *    ilustrativos (não são recomendações técnicas para armazenar qualquer produto).
 *  - Contas de demonstração (senha: Demo@2026): ana.souza, carlos.lima (usuários) e marina.costa (administradora).
 *    A senha dos usuários reais nunca é tocada.
 *  - Não use em banco de produção com clientes reais; para apresentações use um banco separado ou remova depois.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute pelo terminal: php sql/demonstracao.php instalar'); }
define('AKRON_ACESSO_BANCO_LEGADO', true);
require_once __DIR__ . '/../app/config/conexao.php';
require_once __DIR__ . '/../app/services/MigracaoBanco.php';
if (MigracaoBanco::pendente($pdo)) { fwrite(STDERR, "O banco precisa ser atualizado antes (php sql/migrar.php).\n"); exit(1); }

$acao = $argv[1] ?? 'status';
$demoEstoques = (int) $pdo->query('SELECT COUNT(*) FROM estoques WHERE demonstracao=1')->fetchColumn();

if ($acao === 'status') { echo $demoEstoques ? "Há $demoEstoques estoque(s) de demonstração.\n" : "Nenhum dado de demonstração.\n"; exit(0); }

if ($acao === 'remover') {
    $pdo->beginTransaction();
    $ids = $pdo->query('SELECT id FROM estoques WHERE demonstracao=1')->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM alertas WHERE estoque_id IN ($in)");
        $pdo->exec("DELETE FROM leitura_sensor WHERE estoque_id IN ($in)");
        $pdo->exec("DELETE FROM sensores WHERE estoque_id IN ($in)");
        $pdo->exec("DELETE FROM usuario_estoque WHERE estoque_id IN ($in)");
        $pdo->exec("DELETE FROM estoques WHERE id IN ($in)");
    }
    $pdo->exec("DELETE ue FROM usuario_estoque ue JOIN usuario u ON u.id=ue.usuario_id WHERE u.demonstracao=1");
    $pdo->exec("DELETE FROM usuario WHERE demonstracao=1 AND administrador_principal=0");
    $pdo->commit();
    echo "Dados de demonstração removidos. Dados reais preservados.\n"; exit(0);
}

if ($acao === 'atualizar') {
    if (!$demoEstoques) { fwrite(STDERR, "Não há dados de demonstração. Use 'instalar'.\n"); exit(1); }
    $ref = $pdo->query("SELECT MAX(lido_em) FROM leitura_sensor WHERE origem='demonstracao'")->fetchColumn();
    $delta = max(0, time() - strtotime($ref) - 90);
    $pdo->beginTransaction();
    $pdo->exec("UPDATE leitura_sensor SET lido_em=lido_em+INTERVAL $delta SECOND WHERE origem='demonstracao'");
    $pdo->exec("UPDATE sensores s JOIN estoques e ON e.id=s.estoque_id SET s.ultima_comunicacao=s.ultima_comunicacao+INTERVAL $delta SECOND WHERE e.demonstracao=1 AND s.ultima_comunicacao IS NOT NULL");
    foreach (['criado_em', 'reconhecido_em', 'resolvido_em', 'normalizado_em'] as $c)
        $pdo->exec("UPDATE alertas a JOIN estoques e ON e.id=a.estoque_id SET a.$c=a.$c+INTERVAL $delta SECOND WHERE e.demonstracao=1 AND a.$c IS NOT NULL");
    $pdo->commit();
    echo "Datas de demonstração deslocadas em " . round($delta / 3600, 1) . " h.\n"; exit(0);
}

if ($acao !== 'instalar') { fwrite(STDERR, "Uso: php sql/demonstracao.php instalar|atualizar|remover|status\n"); exit(1); }
if ($demoEstoques) { fwrite(STDERR, "Já existem dados de demonstração. Use 'atualizar' ou 'remover'.\n"); exit(1); }

mt_srand(42); // valores reprodutíveis
$agora = time();
$fmt = fn(int $t) => date('Y-m-d H:i:s', $t);
$h = 3600;

// ── Usuários de demonstração ────────────────────────────────────────────────
$senha = password_hash('Demo@2026', PASSWORD_BCRYPT, ['cost' => 12]);
$usuarios = ['ana.souza' => ['Ana Souza', 'usuario'], 'carlos.lima' => ['Carlos Lima', 'usuario'], 'marina.costa' => ['Marina Costa', 'admin']];
$uid = [];
$pdo->beginTransaction();
$existe = $pdo->prepare('SELECT id FROM usuario WHERE login=?');
$novoU = $pdo->prepare('INSERT INTO usuario(nome,login,senha,tipo,status,demonstracao) VALUES(?,?,?,?,?,1)');
foreach ($usuarios as $login => [$nome, $tipo]) {
    $existe->execute([$login]);
    if ($existe->fetchColumn()) { fwrite(STDERR, "O login '$login' já existe e não é de demonstração; nada foi criado.\n"); $pdo->rollBack(); exit(1); }
    $novoU->execute([$nome, $login, $senha, $tipo, 'ativo']);
    $uid[$login] = (int) $pdo->lastInsertId();
}

// ── Estoques e sensores ─────────────────────────────────────────────────────
// sensor: [nome, tipo, localização, limites(atenção_min, atenção_max, crítico_min, crítico_max), base, amplitude, casas,
//          eventos[[início_h_atrás, fim_h_atrás, acréscimo]], parou_h_atrás (null = online), intervalo_leitura_min, lacunas[[de_h, até_h]]]
$estoques = [
 ['Câmara Fria 01', 'Bloco A · Galpão 2', 'Perecíveis refrigerados. Limites de demonstração, apenas ilustrativos.', 'ativo', ['ana.souza'], [
   ['Temperatura – Câmara 01', 'temperatura', 'Parede norte', [1, 6, -2, 8], 3.5, 0.7, 1, [[30, 29, 5.5], [0.7, 0, 3.3]], null, 10, []],
   ['Umidade – Câmara 01', 'umidade', 'Parede norte', [60, 85, 50, 92], 76, 3, 1, [], null, 10, []]]],
 ['Depósito de Medicamentos', 'Sala 3 · Prédio administrativo', 'Medicamentos de controle ambiental. Limites de demonstração, apenas ilustrativos.', 'ativo', ['ana.souza'], [
   ['Temperatura – Sala 3', 'temperatura', 'Prateleira central', [null, 25, null, 30], 21, 1.2, 1, [], null, 10, []],
   ['Umidade – Sala 3', 'umidade', 'Prateleira central', [null, 60, null, 70], 52, 3, 1, [[12, 9, 11]], 6, 10, []]]],
 ['Almoxarifado Central', 'Galpão 1 · Piso térreo', 'Materiais de uso geral e embalagens.', 'ativo', ['carlos.lima'], [
   ['Temperatura – Corredor B', 'temperatura', 'Corredor B', [null, 32, null, 38], 26, 2.5, 1, [], null, 10, []],
   ['Umidade – Corredor B', 'umidade', 'Corredor B', [30, 70, 20, 85], 55, 4, 1, [], null, 10, []],
   ['Qualidade do ar – Doca', 'qualidade_ar', 'Doca de carga', [null, 100, null, 150], 45, 12, 0, [], null, 10, [[20, 17]]],
   ['Ruído – Doca', 'ruido', 'Doca de carga', [null, 85, null, 95], 62, 5, 1, [[2, 1.5, 22], [0.4, 0, 40]], null, 10, []]]],
 ['Sala de Servidores', 'Prédio de TI · 2º andar', 'Equipamentos de rede. Monitoramento interrompido, aguardando manutenção do gateway.', 'ativo', ['carlos.lima'], [
   ['Temperatura – Rack 1', 'temperatura', 'Rack 1', [null, 27, null, 32], 22, 0.8, 1, [[26, 24, 12]], 20, 10, []],
   ['Umidade – Rack 1', 'umidade', 'Rack 1', [30, 60, 20, 75], 45, 2, 1, [], 20, 10, []]]],
 ['Galpão de Embalagens', 'Anexo B', 'Novo galpão: sensores ainda não instalados.', 'ativo', [], []],
 ['Depósito Antigo', 'Rua Projetada, 120', 'Local desativado. Histórico mantido para consulta.', 'inativo', [], [
   ['Temperatura – Depósito Antigo', 'temperatura', 'Entrada', [null, 30, null, 35], 24, 2, 1, [], 72, 15, []]]],
];
$novoE = $pdo->prepare('INSERT INTO estoques(nome,localizacao,descricao,status,demonstracao) VALUES(?,?,?,?,1)');
$novoS = $pdo->prepare('INSERT INTO sensores(nome,tipo,localizacao,status,estoque_id,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max,intervalo_comunicacao,ultima_comunicacao) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
$novoL = $pdo->prepare("INSERT INTO leitura_sensor(sensor_id,estoque_id,valor,lido_em,situacao,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max,origem) VALUES(?,?,?,?,?,?,?,?,?,'demonstracao')");
$novoV = $pdo->prepare('INSERT IGNORE INTO usuario_estoque(usuario_id,estoque_id) VALUES(?,?)');
$mapaSensor = []; $mapaEstoque = []; $totalLeituras = 0;
foreach ($estoques as [$nome, $loc, $desc, $status, $donos, $sensores]) {
    $novoE->execute([$nome, $loc, $desc, $status]); $eid = (int) $pdo->lastInsertId(); $mapaEstoque[$nome] = $eid;
    foreach ($donos as $login) $novoV->execute([$uid[$login], $eid]);
    foreach ($sensores as [$sn, $tipo, $sloc, $lim, $base, $amp, $casas, $eventos, $parou, $passoMin, $lacunas]) {
        $fim = $parou === null ? $agora - 90 : $agora - (int) ($parou * $h);
        $inicio = $status === 'inativo' ? $fim - 24 * $h : $agora - 48 * $h;
        $ativoSensor = $status === 'inativo' ? 'inativo' : 'ativo';
        $novoS->execute([$sn, $tipo, $sloc, $ativoSensor, $eid, $lim[0], $lim[1], $lim[2], $lim[3], 900, $fmt($fim)]);
        $sid = (int) $pdo->lastInsertId(); $mapaSensor[$sn] = [$sid, $eid];
        for ($t = $inicio; $t <= $fim; $t += $passoMin * 60) {
            $horasAtras = ($agora - $t) / $h; $pular = false;
            foreach ($lacunas as [$de, $ate]) if ($horasAtras <= $de && $horasAtras >= $ate) $pular = true;
            if ($pular) continue;
            $v = $base + $amp * sin(2 * M_PI * ($t % 86400) / 86400) + (mt_rand(-100, 100) / 100) * $amp * 0.25;
            foreach ($eventos as [$a, $b, $delta]) if ($horasAtras <= $a && $horasAtras >= $b) $v += $delta;
            $v = round($v, $casas);
            $sit = 'normal';
            foreach (["atencao", "critico"] as $nivel) {
                $min = $nivel === 'atencao' ? $lim[0] : $lim[2]; $max = $nivel === 'atencao' ? $lim[1] : $lim[3];
                if (($min !== null && $v < $min) || ($max !== null && $v > $max)) $sit = $nivel;
            }
            $novoL->execute([$sid, $eid, $v, $fmt($t), $sit, $lim[0], $lim[1], $lim[2], $lim[3]]); $totalLeituras++;
        }
    }
}

// ── Alertas em diferentes situações ─────────────────────────────────────────
$a = $pdo->prepare('INSERT INTO alertas(sensor_id,estoque_id,severidade,mensagem,valor,limite_ultrapassado,criado_em,status,resolvido,reconhecido_por,reconhecido_em,normalizado_em,resolvido_por,resolvido_em,observacoes,automatico) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$crio = fn(string $sensor, string $sev, string $msg, ?float $valor, ?float $limite, float $hAtras, string $status, array $extra = []) => $a->execute([
    $mapaSensor[$sensor][0], $mapaSensor[$sensor][1], $sev, $msg, $valor, $limite, $fmt($agora - (int) ($hAtras * $h)), $status, in_array($status, ['resolvido'], true) ? 1 : 0,
    $extra['rec_por'] ?? null, isset($extra['rec_h']) ? $fmt($agora - (int) ($extra['rec_h'] * $h)) : null,
    isset($extra['norm_h']) ? $fmt($agora - (int) ($extra['norm_h'] * $h)) : null,
    $extra['res_por'] ?? null, isset($extra['res_h']) ? $fmt($agora - (int) ($extra['res_h'] * $h)) : null, $extra['obs'] ?? null, $extra['auto'] ?? 1]);
$crio('Temperatura – Câmara 01', 'critico', 'Temperatura acima do limite crítico de 8 °C', 9.0, 8, 30, 'resolvido', ['rec_por' => $uid['marina.costa'], 'rec_h' => 29.8, 'norm_h' => 29, 'res_por' => $uid['marina.costa'], 'res_h' => 28.5, 'obs' => 'Porta mantida aberta durante a reposição. Vedação conferida e rotina de fechamento reforçada.']);
$crio('Temperatura – Câmara 01', 'atencao', 'Temperatura acima do limite de atenção de 6 °C', 6.8, 6, 0.7, 'pendente');
$crio('Umidade – Sala 3', 'atencao', 'Umidade acima do limite de atenção de 60%', 63.0, 60, 11, 'reconhecido', ['rec_por' => $uid['marina.costa'], 'rec_h' => 10.5, 'norm_h' => 9]);
$crio('Ruído – Doca', 'atencao', 'Ruído acima do limite de atenção de 85 dB', 87.0, 85, 1.9, 'normalizado', ['norm_h' => 1.4]);
$crio('Ruído – Doca', 'critico', 'Ruído acima do limite crítico de 95 dB', 101.0, 95, 0.4, 'pendente');
$crio('Temperatura – Rack 1', 'critico', 'Temperatura acima do limite crítico de 32 °C', 34.0, 32, 26, 'resolvido', ['rec_por' => $uid['marina.costa'], 'rec_h' => 25.6, 'res_por' => $uid['marina.costa'], 'res_h' => 24.5, 'obs' => 'Climatização restabelecida pela equipe de TI.']);
$crio('Qualidade do ar – Doca', 'info', 'Manutenção preventiva do exaustor programada para a próxima semana', null, null, 5, 'pendente', ['auto' => 0]);
$pdo->commit();
echo "Demonstração instalada: " . count($mapaEstoque) . " estoques, " . count($mapaSensor) . " sensores, $totalLeituras leituras simuladas e 3 usuários (senha Demo@2026).\n";
