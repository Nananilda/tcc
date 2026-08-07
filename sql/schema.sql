-- schema.sql
-- Estrutura completa do banco_tcc usada pelos models em app/models/*.php
-- e pelos endpoints em api/*.php. Execute este script no MySQL indicado
-- em app/config/conexao.php (host/porta/usuário/senha já configurados).
--
--   mysql -h 10.140.170.170 -P 3307 -u root -p banco_tcc < sql/schema.sql

CREATE DATABASE IF NOT EXISTS banco_tcc CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE banco_tcc;

-- ── usuario ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuario (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL,
    login     VARCHAR(50)  NOT NULL UNIQUE,
    senha     VARCHAR(255) NOT NULL,
    tipo      ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
    status    ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── sensores ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sensores (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100)  NOT NULL,
    tipo        ENUM('temperatura','ruido','qualidade_ar','umidade','pressao','uv') NOT NULL,
    localizacao VARCHAR(150)  DEFAULT NULL,
    status      ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── alertas ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alertas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_tipo ENUM('temperatura','ruido','qualidade_ar','umidade','pressao','uv') NOT NULL,
    severidade  ENUM('info','atencao','critico') NOT NULL DEFAULT 'info',
    mensagem    VARCHAR(200) NOT NULL,
    valor       DECIMAL(10,2) DEFAULT NULL,
    resolvido   TINYINT(1)   NOT NULL DEFAULT 0,
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── leitura_sensor ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leitura_sensor (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_tipo ENUM('temperatura','ruido','qualidade_ar','umidade','pressao','uv') NOT NULL,
    valor       DECIMAL(10,2) NOT NULL,
    lido_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo_data (sensor_tipo, lido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Usuário administrador inicial ───────────────────────────────────────
-- login: admin   |   senha: Admin@123
-- (hash bcrypt válido para password_verify() do PHP; troque a senha após
-- o primeiro acesso, pela tela de Editar Usuário.)
INSERT INTO usuario (nome, login, senha, tipo, status)
VALUES (
    'Admin de Testes',
    'admin',
    '$2b$12$vIMqDeln9.4HagJQZ.pU/eoORJqNyjLnvbFy2ZqTV39ZkqcjDJTDe',
    'admin',
    'ativo'
)
ON DUPLICATE KEY UPDATE login = login;
