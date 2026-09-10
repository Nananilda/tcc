-- estrutura do banco de dados

DROP DATABASE IF EXISTS banco_tcc;


CREATE DATABASE IF NOT EXISTS banco_tcc
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;


USE banco_tcc;


CREATE TABLE usuario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    login VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('usuario', 'admin') NOT NULL DEFAULT 'usuario',
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sensores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('temperatura', 'ruido', 'qualidade_ar', 'umidade', 'pressao', 'uv') NOT NULL,
    localizacao VARCHAR(150) DEFAULT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leitura_sensor (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT UNSIGNED NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    lido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leitura_sensor
        FOREIGN KEY (sensor_id)
        REFERENCES sensores(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_sensor_data (sensor_id, lido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE alertas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT UNSIGNED NOT NULL,
    severidade ENUM('info', 'atencao', 'critico') NOT NULL DEFAULT 'info',
    mensagem VARCHAR(200) NOT NULL,
    valor DECIMAL(10,2) DEFAULT NULL,
    resolvido TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


INSERT INTO usuario (nome, login, senha, tipo, status)
VALUES
'Admin de Testes',
'admin',
'$2b$12$vIMqDeln9.4HagJQZ.pU/eoORJqNyjLnvbFy2ZqTV39ZkqcjDJTDe',
'admin',
'ativo'

ON DUPLICATE KEY UPDATE login = login;

)