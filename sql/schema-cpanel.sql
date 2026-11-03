-- ÁKRON 3.0 — INSTALAÇÃO NOVA / CPANEL OU BANCO JÁ CRIADO
-- No cPanel, crie um banco vazio e um usuário MySQL e associe o usuário ao banco
-- com as permissões de uso do projeto. Selecione esse banco no phpMyAdmin
-- e importe este arquivo inteiro. Ele usa o banco selecionado e respeita
-- o prefixo do cPanel; não contém CREATE DATABASE nem USE.
-- Configure app/config/conexao.local.php com os nomes completos fornecidos
-- pelo cPanel e o host MySQL da hospedagem.
-- Login no site: admin | Senha inicial: Admin@123. Altere em Minha conta.

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ── usuario ──────────────────────────────────────────────────────────────────
-- Contas de acesso ao sistema (web e app). A coluna `senha` guarda SEMPRE o
-- hash bcrypt gerado por password_hash() — nunca a senha em texto puro.
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

-- ── sensores ─────────────────────────────────────────────────────────────────
-- Cadastro dos sensores instalados na planta. `tipo` define a grandeza medida
-- (e, portanto, a unidade exibida nos gráficos e relatórios).
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

-- ── leitura_sensor ───────────────────────────────────────────────────────────
-- Uma linha por medição enviada por um sensor. Os gráficos e relatórios
-- descobrem o tipo (temperatura, ruído...) fazendo JOIN com `sensores`.
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

-- ── alertas ──────────────────────────────────────────────────────────────────
-- Ocorrências geradas a partir dos sensores. `resolvido_por` registra qual
-- administrador marcou o alerta como resolvido (NULL enquanto pendente).
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

-- ── Usuário administrador inicial ────────────────────────────────────────────
-- login: admin   |   senha: Admin@123
-- (hash bcrypt compatível com password_verify() do PHP). TROQUE a senha após o
-- primeiro acesso, em Minha conta.
INSERT INTO usuario (nome, login, senha, tipo, status, administrador_principal)
VALUES (
    'Administrador',
    'admin',
    '$2y$12$MzgPVFkpgNHnKppm0jGrP.A1X.dhWxOmOozB2m2aAl9R.E.XYWFDq',
    'admin',
    'ativo',
    1
)
ON DUPLICATE KEY UPDATE login = login;

-- Controle interno de versão exigido pelo backend do ZIP entregue.
-- A estrutura completa já foi criada acima. Nenhum script adicional é necessário.
CREATE TABLE IF NOT EXISTS migracoes (
    versao VARCHAR(80) NOT NULL PRIMARY KEY,
    aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO migracoes (versao)
VALUES ('2026_conta_interface_02')
ON DUPLICATE KEY UPDATE versao = versao;

-- Resultado esperado: 8 tabelas criadas e administrador principal disponível.
SELECT 'ÁKRON: banco pronto para conectar ao backend.' AS resultado;
