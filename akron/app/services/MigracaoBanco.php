<?php
/** Atualização compartilhada pelo terminal e pela tela administrativa. */
final class MigracaoBanco
{
    private const VERSAO = '2026_revisao_31';
    /** Versões que já executaram a parte de dados (vínculos e limites iniciais): não repetir. */
    private const VERSOES_ANTERIORES = ['2026_estoques_01', '2026_conta_interface_02'];

    public static function pendente(PDO $pdo): bool
    {
        // Verifica a estrutura real, inclusive quando schema.sql foi importado
        // sobre tabelas antigas ou uma migração foi interrompida.
        $necessarias = [
            'usuario' => ['criado_em','tema','foto','administrador_principal','demonstracao'],
            'estoques' => ['id','nome','descricao','localizacao','status','criado_em','demonstracao'],
            'usuario_estoque' => ['usuario_id','estoque_id'],
            'auditoria' => ['usuario_id','acao','descricao','criado_em'],
            'sensores' => ['estoque_id','limite_atencao_min','limite_atencao_max','limite_critico_min','limite_critico_max','intervalo_comunicacao','ultima_comunicacao'],
            'leitura_sensor' => ['origem','estoque_id','limite_atencao_min','limite_atencao_max','limite_critico_min','limite_critico_max','situacao'],
            'alertas' => ['estoque_id','limite_ultrapassado','status','reconhecido_por','reconhecido_em','resolvido_em','normalizado_em','observacoes','automatico'],
            'migracoes' => ['versao','aplicado_em'],
            'tentativas_login' => ['ip','login','sucesso','criado_em'],
        ];
        $colunas = [];
        foreach ($pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll() as $row) {
            $colunas[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
        }
        foreach ($necessarias as $tabela => $campos) {
            foreach ($campos as $campo) {
                if (!isset($colunas[$tabela][$campo])) return true;
            }
        }
        return !self::registrada($pdo);
    }

    private static function registrada(PDO $pdo, string $versao = self::VERSAO): bool
    {
        $stmt = $pdo->prepare('SELECT versao FROM migracoes WHERE versao=?');
        $stmt->execute([$versao]);
        return (bool) $stmt->fetchColumn();
    }

    private static function coluna(PDO $pdo, string $t, string $c, string $tipo): void
    {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $q->execute([$t, $c]);
        if (!$q->fetchColumn()) $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $tipo");
    }

    private static function fk(PDO $pdo, string $t, string $nome, string $campo, string $destino, string $delete='RESTRICT'): void
    {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
        $q->execute([$t, $nome]);
        if (!$q->fetchColumn()) $pdo->exec("ALTER TABLE `$t` ADD CONSTRAINT `$nome` FOREIGN KEY (`$campo`) REFERENCES `$destino`(id) ON DELETE $delete ON UPDATE CASCADE");
    }

    public static function executar(PDO $pdo): bool
    {
        $lock = 'akron_migrar_' . sha1((string) $pdo->query('SELECT DATABASE()')->fetchColumn());
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$lock]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Outra atualização está em andamento. Aguarde e tente novamente.');
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS migracoes (versao VARCHAR(80) PRIMARY KEY, aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            if (!self::pendente($pdo)) return false;
            $jaAplicada = self::registrada($pdo);
            foreach (self::VERSOES_ANTERIORES as $anterior) $jaAplicada = $jaAplicada || self::registrada($pdo, $anterior);
            // DDL no MySQL confirma transações implicitamente. Cada etapa é retomável;
            // a versão só é marcada após a migração de dados e todos os vínculos.
            $schema=file_get_contents(__DIR__.'/../../sql/schema.sql');
            preg_match_all('/CREATE TABLE IF NOT EXISTS (?:estoques|usuario_estoque|auditoria)\s*\(.*?;\s*/s',$schema,$matches);
            foreach ($matches[0] as $sql) $pdo->exec($sql);
            self::coluna($pdo,'usuario','criado_em','DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
            $pdo->exec("UPDATE usuario SET criado_em=NOW() WHERE criado_em IS NULL OR TRIM(CAST(criado_em AS CHAR))='' OR CAST(criado_em AS CHAR)='0000-00-00 00:00:00'");
            $pdo->exec('ALTER TABLE usuario MODIFY criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
            foreach (['sensores','leitura_sensor','alertas'] as $t) self::coluna($pdo,$t,'estoque_id','INT UNSIGNED NULL');
            foreach (['sensores','leitura_sensor'] as $t) foreach (['atencao_min','atencao_max','critico_min','critico_max'] as $c) self::coluna($pdo,$t,'limite_'.$c,'DECIMAL(10,2) NULL');
            self::coluna($pdo,'sensores','intervalo_comunicacao','INT UNSIGNED NOT NULL DEFAULT 300');
            self::coluna($pdo,'sensores','ultima_comunicacao','DATETIME NULL');
            self::coluna($pdo,'leitura_sensor','situacao',"ENUM('normal','atencao','critico') NOT NULL DEFAULT 'normal'");
            foreach (['limite_ultrapassado'=>'DECIMAL(10,2) NULL','status'=>"ENUM('pendente','reconhecido','normalizado','resolvido') NOT NULL DEFAULT 'pendente'",'reconhecido_por'=>'INT UNSIGNED NULL','reconhecido_em'=>'DATETIME NULL','resolvido_em'=>'DATETIME NULL','normalizado_em'=>'DATETIME NULL','observacoes'=>'TEXT NULL','automatico'=>'TINYINT(1) NOT NULL DEFAULT 0'] as $c=>$tipo) self::coluna($pdo,'alertas',$c,$tipo);
            self::coluna($pdo,'usuario','tema',"ENUM('claro','escuro','automatico') NOT NULL DEFAULT 'automatico'");
            self::coluna($pdo,'usuario','foto','MEDIUMTEXT NULL');
            self::coluna($pdo,'usuario','administrador_principal','TINYINT(1) NOT NULL DEFAULT 0');
            // Revisão 3.1: identificação de dados de demonstração, origem das leituras e controle de tentativas de login.
            self::coluna($pdo,'usuario','demonstracao','TINYINT(1) NOT NULL DEFAULT 0');
            self::coluna($pdo,'estoques','demonstracao','TINYINT(1) NOT NULL DEFAULT 0');
            // Leituras anteriores não têm origem conhecida: ficam como 'nao_informada', nunca presumidas como de sensor físico.
            self::coluna($pdo,'leitura_sensor','origem',"ENUM('sensor','manual','demonstracao','nao_informada') NOT NULL DEFAULT 'nao_informada'");
            require_once __DIR__ . '/../../includes/auth.php';
            garantirTabelaTentativas($pdo);
            if (!(int)$pdo->query('SELECT COUNT(*) FROM usuario WHERE administrador_principal=1')->fetchColumn()) {
                $principal=$pdo->query("SELECT id FROM usuario WHERE tipo='admin' ORDER BY id LIMIT 1")->fetchColumn();
                if ($principal) $pdo->prepare('UPDATE usuario SET administrador_principal=1 WHERE id=?')->execute([$principal]);
            }
            $pdo->beginTransaction();
            $initial=$pdo->query("SELECT id FROM estoques WHERE nome='Estoque inicial' ORDER BY id LIMIT 1")->fetchColumn();
            if (!$initial) { $pdo->exec("INSERT INTO estoques(nome,descricao,localizacao) VALUES ('Estoque inicial','Sensores e histórico preservados da instalação anterior.','Não informada')"); $initial=$pdo->lastInsertId(); }
            $initial=(int)$initial;
            $pdo->exec("UPDATE sensores SET estoque_id=$initial WHERE estoque_id IS NULL");
            if (!$jaAplicada) $pdo->exec("INSERT IGNORE INTO usuario_estoque (usuario_id,estoque_id) SELECT id,$initial FROM usuario");
            // Copia os limites existentes no código antigo; não altera regras de negócio.
            // Valores genéricos aplicados uma única vez, e só onde TODOS os limites do sensor estavam vazios (instalações da versão 2).
            // Não têm validação documental: ver docs/LIMITES-AMBIENTAIS.md.
            $regras=['temperatura'=>[null,30,null,35],'ruido'=>[null,85,null,95],'qualidade_ar'=>[null,100,null,150],'umidade'=>[35,80,20,90],'pressao'=>[1000,1030,990,1040],'uv'=>[null,6,null,8]];
            $q=$pdo->prepare('UPDATE sensores SET limite_atencao_min=?,limite_atencao_max=?,limite_critico_min=?,limite_critico_max=? WHERE tipo=? AND limite_atencao_min IS NULL AND limite_atencao_max IS NULL AND limite_critico_min IS NULL AND limite_critico_max IS NULL');
            if (!$jaAplicada) foreach ($regras as $tipo=>$valores) $q->execute([...$valores,$tipo]);
            $pdo->exec('UPDATE sensores s SET ultima_comunicacao=(SELECT MAX(l.lido_em) FROM leitura_sensor l WHERE l.sensor_id=s.id) WHERE ultima_comunicacao IS NULL');
            $pdo->exec("UPDATE leitura_sensor l JOIN sensores s ON s.id=l.sensor_id SET l.estoque_id=s.estoque_id,l.limite_atencao_min=s.limite_atencao_min,l.limite_atencao_max=s.limite_atencao_max,l.limite_critico_min=s.limite_critico_min,l.limite_critico_max=s.limite_critico_max,l.situacao=CASE WHEN l.valor<s.limite_critico_min OR l.valor>s.limite_critico_max THEN 'critico' WHEN l.valor<s.limite_atencao_min OR l.valor>s.limite_atencao_max THEN 'atencao' ELSE 'normal' END WHERE l.estoque_id IS NULL");
            $pdo->exec('UPDATE alertas a JOIN sensores s ON s.id=a.sensor_id SET a.estoque_id=s.estoque_id WHERE a.estoque_id IS NULL');
            // Datas de resolução antigas não podem ser inferidas: mantidas NULL.
            $pdo->exec("UPDATE alertas SET status='resolvido' WHERE resolvido=1 AND status='pendente'");
            $pdo->commit();
            foreach (['sensores','leitura_sensor','alertas'] as $t) $pdo->exec("ALTER TABLE `$t` MODIFY estoque_id INT UNSIGNED NOT NULL");
            self::fk($pdo,'sensores','fk_sensor_estoque','estoque_id','estoques');
            self::fk($pdo,'leitura_sensor','fk_leitura_estoque','estoque_id','estoques');
            self::fk($pdo,'alertas','fk_alerta_estoque','estoque_id','estoques');
            self::fk($pdo,'alertas','fk_alerta_reconhecido','reconhecido_por','usuario','SET NULL');
            // Substitui CASCADE legado por RESTRICT, sem excluir nenhum registro.
            foreach (['leitura_sensor'=>'fk_leitura_sensor','alertas'=>'fk_alerta_sensor'] as $t=>$nome) {
                $q=$pdo->prepare("SELECT CONSTRAINT_NAME,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME='sensores'"); $q->execute([$t]);
                foreach ($q->fetchAll() as $constraint) if ($constraint['DELETE_RULE']!=='RESTRICT' && $constraint['DELETE_RULE']!=='NO ACTION') { $safe=str_replace('`','``',$constraint['CONSTRAINT_NAME']); $pdo->exec("ALTER TABLE `$t` DROP FOREIGN KEY `$safe`"); }
                self::fk($pdo,$t,$nome,'sensor_id','sensores');
            }
            $q=$pdo->prepare('INSERT IGNORE INTO migracoes(versao) VALUES(?)'); $q->execute([self::VERSAO]);
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        } finally {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lock]);
        }
    }
}
