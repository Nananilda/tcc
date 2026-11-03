-- Execute este arquivo inteiro no MySQL Workbench.
-- Usa banco_tcc existente. Acrescenta a estrutura que faltar e conserva os dados.
USE banco_tcc;
SET NAMES utf8mb4;
SET @akron_safe_updates = @@SESSION.sql_safe_updates;
SET @akron_group_concat = @@SESSION.group_concat_max_len;
SET SESSION sql_safe_updates = 0;
SET SESSION group_concat_max_len = 16384;

SET @akron_sem_vinculos = NOT EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuario_estoque'
);
SET @akron_limites_novos = NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sensores'
      AND COLUMN_NAME IN ('limite_atencao_min','limite_atencao_max','limite_critico_min','limite_critico_max')
);
SET @akron_historico_novo = NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leitura_sensor' AND COLUMN_NAME='estoque_id'
);
SET @akron_status_alerta_novo = NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alertas' AND COLUMN_NAME='status'
);

-- Tabelas que já existem são mantidas.

CREATE TABLE IF NOT EXISTS usuario (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL,
    login     VARCHAR(100) NOT NULL UNIQUE,
    senha     VARCHAR(255) NOT NULL,
    tipo      ENUM('usuario', 'admin') NOT NULL DEFAULT 'usuario',
    status    ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tema ENUM('claro','escuro','automatico') NOT NULL DEFAULT 'automatico',
    foto MEDIUMTEXT NULL,
    administrador_principal TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estoques (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    localizacao VARCHAR(150) NULL,
    status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_estoque (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    estoque_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_estoque (usuario_id,estoque_id),
    FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (estoque_id) REFERENCES estoques(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    acao VARCHAR(80) NOT NULL,
    descricao TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditoria_data (criado_em),
    FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sensores (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    tipo        ENUM('temperatura', 'ruido', 'qualidade_ar', 'umidade', 'pressao', 'uv') NOT NULL,
    localizacao VARCHAR(150) DEFAULT NULL,
    status      ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estoque_id INT UNSIGNED NOT NULL,
    limite_atencao_min DECIMAL(10,2) NULL,
    limite_atencao_max DECIMAL(10,2) NULL,
    limite_critico_min DECIMAL(10,2) NULL,
    limite_critico_max DECIMAL(10,2) NULL,
    intervalo_comunicacao INT UNSIGNED NOT NULL DEFAULT 300,
    ultima_comunicacao DATETIME NULL,
    CONSTRAINT fk_sensor_estoque FOREIGN KEY (estoque_id) REFERENCES estoques(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_sensor_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leitura_sensor (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT UNSIGNED NOT NULL,
    valor     DECIMAL(10,2) NOT NULL,
    lido_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estoque_id INT UNSIGNED NOT NULL,
    situacao ENUM('normal','atencao','critico') NOT NULL DEFAULT 'normal',
    limite_atencao_min DECIMAL(10,2) NULL,
    limite_atencao_max DECIMAL(10,2) NULL,
    limite_critico_min DECIMAL(10,2) NULL,
    limite_critico_max DECIMAL(10,2) NULL,
    CONSTRAINT fk_leitura_estoque FOREIGN KEY (estoque_id) REFERENCES estoques(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_leitura_sensor
        FOREIGN KEY (sensor_id)
        REFERENCES sensores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    INDEX idx_sensor_data (sensor_id, lido_em),
    INDEX idx_leitura_data (lido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alertas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id     INT UNSIGNED NOT NULL,
    severidade    ENUM('info', 'atencao', 'critico') NOT NULL DEFAULT 'info',
    mensagem      VARCHAR(200) NOT NULL,
    valor         DECIMAL(10,2) DEFAULT NULL,
    resolvido     TINYINT(1) NOT NULL DEFAULT 0,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_por INT UNSIGNED DEFAULT NULL,
    estoque_id INT UNSIGNED NOT NULL,
    limite_ultrapassado DECIMAL(10,2) NULL,
    status ENUM('pendente','reconhecido','normalizado','resolvido') NOT NULL DEFAULT 'pendente',
    reconhecido_por INT UNSIGNED NULL,
    reconhecido_em DATETIME NULL,
    resolvido_em DATETIME NULL,
    normalizado_em DATETIME NULL,
    observacoes TEXT NULL,
    automatico TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_alerta_estoque FOREIGN KEY (estoque_id) REFERENCES estoques(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_alerta_reconhecido FOREIGN KEY (reconhecido_por) REFERENCES usuario(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_alerta_sensor
        FOREIGN KEY (sensor_id)
        REFERENCES sensores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_alerta_usuario
        FOREIGN KEY (resolvido_por)
        REFERENCES usuario(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    INDEX idx_alerta_sensor (sensor_id),
    INDEX idx_alerta_data (criado_em),
    INDEX idx_alerta_resolvido (resolvido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migracoes (
    versao VARCHAR(80) NOT NULL PRIMARY KEY,
    aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acrescenta somente as colunas ausentes em usuario.
SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', c.coluna, '` ', c.definicao) SEPARATOR ', ')
INTO @akron_adicoes
FROM (
    SELECT 'criado_em' AS coluna, 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' AS definicao
    UNION ALL SELECT 'tema' AS coluna, 'ENUM(''claro'',''escuro'',''automatico'') NOT NULL DEFAULT ''automatico''' AS definicao
    UNION ALL SELECT 'foto' AS coluna, 'MEDIUMTEXT NULL' AS definicao
    UNION ALL SELECT 'administrador_principal' AS coluna, 'TINYINT(1) NOT NULL DEFAULT 0' AS definicao
) c
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS e
    WHERE e.TABLE_SCHEMA=DATABASE() AND e.TABLE_NAME='usuario' AND e.COLUMN_NAME=c.coluna
);
SET @akron_sql = IF(@akron_adicoes IS NULL, 'DO 0', CONCAT('ALTER TABLE `usuario` ', @akron_adicoes));
PREPARE akron_stmt FROM @akron_sql;
EXECUTE akron_stmt;
DEALLOCATE PREPARE akron_stmt;


-- Acrescenta somente as colunas ausentes em estoques.
SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', c.coluna, '` ', c.definicao) SEPARATOR ', ')
INTO @akron_adicoes
FROM (
    SELECT 'descricao' AS coluna, 'TEXT NULL' AS definicao
    UNION ALL SELECT 'localizacao' AS coluna, 'VARCHAR(150) NULL' AS definicao
    UNION ALL SELECT 'status' AS coluna, 'ENUM(''ativo'',''inativo'') NOT NULL DEFAULT ''ativo''' AS definicao
    UNION ALL SELECT 'criado_em' AS coluna, 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' AS definicao
) c
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS e
    WHERE e.TABLE_SCHEMA=DATABASE() AND e.TABLE_NAME='estoques' AND e.COLUMN_NAME=c.coluna
);
SET @akron_sql = IF(@akron_adicoes IS NULL, 'DO 0', CONCAT('ALTER TABLE `estoques` ', @akron_adicoes));
PREPARE akron_stmt FROM @akron_sql;
EXECUTE akron_stmt;
DEALLOCATE PREPARE akron_stmt;


-- Acrescenta somente as colunas ausentes em usuario_estoque.
SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', c.coluna, '` ', c.definicao) SEPARATOR ', ')
INTO @akron_adicoes
FROM (
    SELECT 'criado_em' AS coluna, 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' AS definicao
) c
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS e
    WHERE e.TABLE_SCHEMA=DATABASE() AND e.TABLE_NAME='usuario_estoque' AND e.COLUMN_NAME=c.coluna
);
SET @akron_sql = IF(@akron_adicoes IS NULL, 'DO 0', CONCAT('ALTER TABLE `usuario_estoque` ', @akron_adicoes));
PREPARE akron_stmt FROM @akron_sql;
EXECUTE akron_stmt;
DEALLOCATE PREPARE akron_stmt;


-- Acrescenta somente as colunas ausentes em sensores.
SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', c.coluna, '` ', c.definicao) SEPARATOR ', ')
INTO @akron_adicoes
FROM (
    SELECT 'estoque_id' AS coluna, 'INT UNSIGNED NULL' AS definicao
    UNION ALL SELECT 'limite_atencao_min' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'limite_atencao_max' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'limite_critico_min' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'limite_critico_max' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'intervalo_comunicacao' AS coluna, 'INT UNSIGNED NOT NULL DEFAULT 300' AS definicao
    UNION ALL SELECT 'ultima_comunicacao' AS coluna, 'DATETIME NULL' AS definicao
) c
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS e
    WHERE e.TABLE_SCHEMA=DATABASE() AND e.TABLE_NAME='sensores' AND e.COLUMN_NAME=c.coluna
);
SET @akron_sql = IF(@akron_adicoes IS NULL, 'DO 0', CONCAT('ALTER TABLE `sensores` ', @akron_adicoes));
PREPARE akron_stmt FROM @akron_sql;
EXECUTE akron_stmt;
DEALLOCATE PREPARE akron_stmt;


-- Acrescenta somente as colunas ausentes em leitura_sensor.
SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', c.coluna, '` ', c.definicao) SEPARATOR ', ')
INTO @akron_adicoes
FROM (
    SELECT 'estoque_id' AS coluna, 'INT UNSIGNED NULL' AS definicao
    UNION ALL SELECT 'situacao' AS coluna, 'ENUM(''normal'',''atencao'',''critico'') NOT NULL DEFAULT ''normal''' AS definicao
    UNION ALL SELECT 'limite_atencao_min' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'limite_atencao_max' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'limite_critico_min' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'limite_critico_max' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
) c
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS e
    WHERE e.TABLE_SCHEMA=DATABASE() AND e.TABLE_NAME='leitura_sensor' AND e.COLUMN_NAME=c.coluna
);
SET @akron_sql = IF(@akron_adicoes IS NULL, 'DO 0', CONCAT('ALTER TABLE `leitura_sensor` ', @akron_adicoes));
PREPARE akron_stmt FROM @akron_sql;
EXECUTE akron_stmt;
DEALLOCATE PREPARE akron_stmt;


-- Acrescenta somente as colunas ausentes em alertas.
SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', c.coluna, '` ', c.definicao) SEPARATOR ', ')
INTO @akron_adicoes
FROM (
    SELECT 'estoque_id' AS coluna, 'INT UNSIGNED NULL' AS definicao
    UNION ALL SELECT 'limite_ultrapassado' AS coluna, 'DECIMAL(10,2) NULL' AS definicao
    UNION ALL SELECT 'status' AS coluna, 'ENUM(''pendente'',''reconhecido'',''normalizado'',''resolvido'') NOT NULL DEFAULT ''pendente''' AS definicao
    UNION ALL SELECT 'reconhecido_por' AS coluna, 'INT UNSIGNED NULL' AS definicao
    UNION ALL SELECT 'reconhecido_em' AS coluna, 'DATETIME NULL' AS definicao
    UNION ALL SELECT 'resolvido_em' AS coluna, 'DATETIME NULL' AS definicao
    UNION ALL SELECT 'normalizado_em' AS coluna, 'DATETIME NULL' AS definicao
    UNION ALL SELECT 'observacoes' AS coluna, 'TEXT NULL' AS definicao
    UNION ALL SELECT 'automatico' AS coluna, 'TINYINT(1) NOT NULL DEFAULT 0' AS definicao
) c
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS e
    WHERE e.TABLE_SCHEMA=DATABASE() AND e.TABLE_NAME='alertas' AND e.COLUMN_NAME=c.coluna
);
SET @akron_sql = IF(@akron_adicoes IS NULL, 'DO 0', CONCAT('ALTER TABLE `alertas` ', @akron_adicoes));
PREPARE akron_stmt FROM @akron_sql;
EXECUTE akron_stmt;
DEALLOCATE PREPARE akron_stmt;


-- Mantém os sensores e seu histórico visíveis quando ainda não tinham estoque.
SET @akron_precisa_estoque = EXISTS (SELECT 1 FROM sensores WHERE estoque_id IS NULL);
INSERT INTO estoques (nome,descricao,localizacao,status)
SELECT 'Estoque inicial','Sensores e histórico já existentes.','Não informada','ativo'
WHERE @akron_precisa_estoque
  AND NOT EXISTS (SELECT 1 FROM estoques WHERE nome='Estoque inicial');
SET @akron_estoque = (SELECT MIN(id) FROM estoques WHERE nome='Estoque inicial');
UPDATE sensores SET estoque_id=@akron_estoque WHERE estoque_id IS NULL;
INSERT INTO usuario_estoque (usuario_id,estoque_id)
SELECT u.id,@akron_estoque FROM usuario u
WHERE @akron_sem_vinculos AND @akron_precisa_estoque
  AND NOT EXISTS (SELECT 1 FROM usuario_estoque ue WHERE ue.usuario_id=u.id AND ue.estoque_id=@akron_estoque);

-- Limites que o projeto já utilizava, apenas se essas colunas ainda não existiam.
UPDATE sensores SET
    limite_atencao_min=CASE tipo WHEN 'umidade' THEN 35 WHEN 'pressao' THEN 1000 ELSE NULL END,
    limite_atencao_max=CASE tipo WHEN 'temperatura' THEN 30 WHEN 'ruido' THEN 85 WHEN 'qualidade_ar' THEN 100 WHEN 'umidade' THEN 80 WHEN 'pressao' THEN 1030 WHEN 'uv' THEN 6 END,
    limite_critico_min=CASE tipo WHEN 'umidade' THEN 20 WHEN 'pressao' THEN 990 ELSE NULL END,
    limite_critico_max=CASE tipo WHEN 'temperatura' THEN 35 WHEN 'ruido' THEN 95 WHEN 'qualidade_ar' THEN 150 WHEN 'umidade' THEN 90 WHEN 'pressao' THEN 1040 WHEN 'uv' THEN 8 END
WHERE @akron_limites_novos;
UPDATE leitura_sensor l JOIN sensores s ON s.id=l.sensor_id
SET l.estoque_id=s.estoque_id WHERE l.estoque_id IS NULL;
UPDATE leitura_sensor l JOIN sensores s ON s.id=l.sensor_id
SET l.limite_atencao_min=s.limite_atencao_min,l.limite_atencao_max=s.limite_atencao_max,
    l.limite_critico_min=s.limite_critico_min,l.limite_critico_max=s.limite_critico_max,
    l.situacao=CASE WHEN l.valor<s.limite_critico_min OR l.valor>s.limite_critico_max THEN 'critico'
                   WHEN l.valor<s.limite_atencao_min OR l.valor>s.limite_atencao_max THEN 'atencao' ELSE 'normal' END
WHERE @akron_historico_novo;
UPDATE alertas a JOIN sensores s ON s.id=a.sensor_id
SET a.estoque_id=s.estoque_id WHERE a.estoque_id IS NULL;
UPDATE alertas SET status='resolvido' WHERE @akron_status_alerta_novo AND resolvido=1;
UPDATE sensores s SET ultima_comunicacao=(SELECT MAX(l.lido_em) FROM leitura_sensor l WHERE l.sensor_id=s.id AND l.estoque_id=s.estoque_id)
WHERE s.ultima_comunicacao IS NULL;

-- Protege o primeiro administrador existente, mantendo login, senha e perfil.
SET @akron_principal = (SELECT MIN(id) FROM usuario WHERE tipo='admin');
SET @akron_tem_principal = EXISTS (SELECT 1 FROM usuario WHERE administrador_principal=1);
UPDATE usuario SET administrador_principal=1 WHERE id=@akron_principal AND NOT @akron_tem_principal;

-- Registro interno esperado pelo backend já entregue.
INSERT INTO migracoes (versao) VALUES ('2026_conta_interface_02')
ON DUPLICATE KEY UPDATE versao=versao;

SET SESSION sql_safe_updates = @akron_safe_updates;
SET SESSION group_concat_max_len = @akron_group_concat;
SELECT 'Estrutura do banco_tcc pronta para o projeto.' AS resultado;
