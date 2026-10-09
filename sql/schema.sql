-- =============================================================================
-- ÁKRON — Sistema de Monitoramento Industrial
-- sql/schema.sql — estrutura do banco de dados (MySQL 5.7+ / MariaDB 10.3+)
--
-- Usado por: app/models/*.php, app/controllers/*.php e api/*.php.
--
-- COMO EXECUTAR (ajuste host/porta/usuário conforme app/config/conexao.php):
--   mysql -h 127.0.0.1 -P 3306 -u root -p < sql/schema.sql
--
-- ⚠ ATENÇÃO: este script APAGA e recria o banco `banco_tcc` do zero.
--   Use-o apenas na instalação inicial ou para reiniciar o ambiente de testes.
--
-- Depois de criar a estrutura, carregue (opcional) os dados de demonstração:
--   mysql -h 127.0.0.1 -P 3306 -u root -p banco_tcc < sql/dados_exemplo.sql
-- =============================================================================

-- Garante UTF-8 na conexão, qualquer que seja o charset padrão do cliente
-- (sem isto, acentos podem ser gravados com codificação dupla: "GalpÃ£o").
SET NAMES utf8mb4;

-- Fuso horário de Brasília (o mesmo que o PHP usa em app/config/app.php): as datas gravadas
-- por este script (NOW()) ficam coerentes com as que o sistema exibe, mesmo que o servidor
-- MySQL esteja configurado em UTC.
SET time_zone = '-03:00';

DROP DATABASE IF EXISTS banco_tcc;

CREATE DATABASE banco_tcc
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE banco_tcc;

-- ── usuario ──────────────────────────────────────────────────────────────────
-- Contas de acesso ao sistema (web e app). A coluna `senha` guarda SEMPRE o
-- hash bcrypt gerado por password_hash() — nunca a senha em texto puro.
CREATE TABLE usuario (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL,
    login     VARCHAR(100) NOT NULL UNIQUE,
    senha     VARCHAR(255) NOT NULL,
    tipo      ENUM('usuario', 'admin') NOT NULL DEFAULT 'usuario',
    status    ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── sensores ─────────────────────────────────────────────────────────────────
-- Cadastro dos sensores instalados na planta. `tipo` define a grandeza medida
-- (e, portanto, a unidade exibida nos gráficos e relatórios).
CREATE TABLE sensores (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    tipo        ENUM('temperatura', 'ruido', 'qualidade_ar', 'umidade', 'pressao', 'uv') NOT NULL,
    localizacao VARCHAR(150) DEFAULT NULL,
    status      ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sensor_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── leitura_sensor ───────────────────────────────────────────────────────────
-- Uma linha por medição enviada por um sensor. Os gráficos e relatórios
-- descobrem o tipo (temperatura, ruído...) fazendo JOIN com `sensores`.
CREATE TABLE leitura_sensor (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT UNSIGNED NOT NULL,
    valor     DECIMAL(10,2) NOT NULL,
    lido_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leitura_sensor
        FOREIGN KEY (sensor_id)
        REFERENCES sensores(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_sensor_data (sensor_id, lido_em),
    INDEX idx_leitura_data (lido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── alertas ──────────────────────────────────────────────────────────────────
-- Ocorrências geradas a partir dos sensores. `resolvido_por` registra qual
-- administrador marcou o alerta como resolvido (NULL enquanto pendente).
CREATE TABLE alertas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id     INT UNSIGNED NOT NULL,
    severidade    ENUM('info', 'atencao', 'critico') NOT NULL DEFAULT 'info',
    mensagem      VARCHAR(200) NOT NULL,
    valor         DECIMAL(10,2) DEFAULT NULL,
    resolvido     TINYINT(1) NOT NULL DEFAULT 0,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_por INT UNSIGNED DEFAULT NULL,
    CONSTRAINT fk_alerta_sensor
        FOREIGN KEY (sensor_id)
        REFERENCES sensores(id)
        ON DELETE CASCADE
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

-- ── Usuário administrador inicial ────────────────────────────────────────────
-- login: admin   |   senha: Admin@123
-- (hash bcrypt compatível com password_verify() do PHP). TROQUE a senha após o
-- primeiro acesso, em Gestão de Cadastro → Editar Usuário.
INSERT INTO usuario (nome, login, senha, tipo, status)
VALUES (
    'Admin de Testes',
    'admin',
    '$2y$12$MzgPVFkpgNHnKppm0jGrP.A1X.dhWxOmOozB2m2aAl9R.E.XYWFDq',
    'admin',
    'ativo'
)
ON DUPLICATE KEY UPDATE login = login;
