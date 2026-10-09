-- =============================================================================
-- ÁKRON — Sistema de Monitoramento Industrial
-- sql/dados_exemplo.sql — dados de demonstração (opcional)
--
-- Popula o banco com sensores, 7 dias de leituras, alertas e usuários de teste,
-- para que painel, gráficos, alertas e relatórios mostrem informação real.
-- Todas as datas são relativas a NOW(): rode o script de novo sempre que
-- quiser "atualizar" a janela de dados (ex.: antes de uma apresentação).
--
-- COMO EXECUTAR (depois de sql/schema.sql):
--   mysql -h 127.0.0.1 -P 3306 -u root -p banco_tcc < sql/dados_exemplo.sql
--
-- ⚠ ATENÇÃO: apaga os dados das tabelas `sensores`, `leitura_sensor` e
--   `alertas` antes de recriá-los. A tabela `usuario` é preservada (os
--   usuários de exemplo são inseridos apenas se ainda não existirem).
--
-- Contas de teste (senha dos usuários comuns: Usuario@123):
--   admin / Admin@123   → Administrador   (criado em schema.sql)
--   usuario             → Funcionário
--   maria.souza         → Funcionário
--   joao.silva          → Funcionário (conta INATIVA, para testar o bloqueio)
-- =============================================================================

-- Garante UTF-8 na conexão, qualquer que seja o charset padrão do cliente
-- (sem isto, acentos podem ser gravados com codificação dupla: "GalpÃ£o").
SET NAMES utf8mb4;

-- Fuso horário de Brasília (o mesmo que o PHP usa em app/config/app.php): as datas gravadas
-- por este script (NOW()) ficam coerentes com as que o sistema exibe, mesmo que o servidor
-- MySQL esteja configurado em UTC.
SET time_zone = '-03:00';

USE banco_tcc;

-- ── Limpeza dos dados de monitoramento ───────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE alertas;
TRUNCATE TABLE leitura_sensor;
TRUNCATE TABLE sensores;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Usuários de exemplo (hash bcrypt de "Usuario@123") ───────────────────────
INSERT INTO usuario (nome, login, senha, tipo, status) VALUES
    ('Usuário Comum', 'usuario',     '$2y$12$871oVLor.Rm0VqHxj5r3GuER3WdYnqqFTjI0UG3fg3prYY3I9Wql.', 'usuario', 'ativo'),
    ('Maria Souza',   'maria.souza', '$2y$12$871oVLor.Rm0VqHxj5r3GuER3WdYnqqFTjI0UG3fg3prYY3I9Wql.', 'usuario', 'ativo'),
    ('João Silva',    'joao.silva',  '$2y$12$871oVLor.Rm0VqHxj5r3GuER3WdYnqqFTjI0UG3fg3prYY3I9Wql.', 'usuario', 'inativo')
ON DUPLICATE KEY UPDATE login = login;

-- ── Sensores (ids 1 a 7, porque a tabela acabou de ser reiniciada) ───────────
INSERT INTO sensores (nome, tipo, localizacao, status, criado_em) VALUES
    ('Sensor Galpão A',             'temperatura',  'Galpão A, linha 1',  'ativo',   NOW() - INTERVAL 40 DAY),
    ('Sensor Galpão B',             'temperatura',  'Galpão B, linha 3',  'ativo',   NOW() - INTERVAL 40 DAY),
    ('Decibelímetro Prensa 02',     'ruido',        'Setor de prensas',   'ativo',   NOW() - INTERVAL 32 DAY),
    ('Monitor de Ar — Soldagem',    'qualidade_ar', 'Setor de soldagem',  'ativo',   NOW() - INTERVAL 28 DAY),
    ('Higrômetro Estoque',          'umidade',      'Estoque central',    'ativo',   NOW() - INTERVAL 21 DAY),
    ('Barômetro Sala de Controle',  'pressao',      'Sala de controle',   'ativo',   NOW() - INTERVAL 21 DAY),
    ('Sensor UV Pátio Externo',     'uv',           'Pátio externo',      'inativo', NOW() - INTERVAL 14 DAY);

-- ── Leituras: 1 por hora nos últimos 7 dias (0 a 168 h atrás) ────────────────
-- Tabela auxiliar com os inteiros 0..168 (monta-se com dígitos, sem CTE, para
-- funcionar em qualquer versão do MySQL/MariaDB).
CREATE TEMPORARY TABLE _horas (n INT NOT NULL PRIMARY KEY);
INSERT INTO _horas (n)
SELECT a.d + 10 * b.d + 100 * c.d
FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
CROSS JOIN (SELECT 0 d UNION ALL SELECT 1) c
WHERE a.d + 10 * b.d + 100 * c.d <= 168;

-- Cada tipo de sensor segue um ciclo diário (seno em função da hora do dia) +
-- um "ruído" determinístico `r` entre 0 e 1. Nos instantes dos alertas
-- cadastrados mais abaixo, a leitura é fixada no valor do alerta (e nos pontos
-- vizinhos, um valor próximo), para o gráfico mostrar exatamente o pico citado.
INSERT INTO leitura_sensor (sensor_id, valor, lido_em)
SELECT q.id,
       ROUND(CASE
           -- picos que originaram os alertas (n = horas atrás)
           WHEN q.id = 1 AND q.n = 6              THEN 36.40
           WHEN q.id = 1 AND q.n IN (5, 7)        THEN 34.90
           WHEN q.id = 3 AND q.n = 3              THEN 97.20
           WHEN q.id = 3 AND q.n IN (2, 4)        THEN 88.50
           WHEN q.id = 4 AND q.n = 11             THEN 132.00
           WHEN q.id = 4 AND q.n IN (10, 12)      THEN 118.00
           WHEN q.id = 5 AND q.n = 20             THEN 33.10
           WHEN q.id = 5 AND q.n IN (19, 21)      THEN 36.40
           WHEN q.id = 6 AND q.n = 48             THEN 1004.20
           WHEN q.id = 6 AND q.n IN (47, 49)      THEN 1006.00
           ELSE CASE q.tipo
               WHEN 'temperatura' THEN
                   22 + 5 * SIN((HOUR(q.lido_em) - 9) * PI() / 12) + (q.r - 0.5) * 1.2 + (q.id - 1) * 1.8
               WHEN 'ruido' THEN
                   58 + CASE WHEN HOUR(q.lido_em) BETWEEN 7 AND 17 THEN 14 ELSE 0 END + (q.r - 0.5) * 8
               WHEN 'qualidade_ar' THEN
                   55 + 25 * SIN((HOUR(q.lido_em) - 8) * PI() / 12) + (q.r - 0.5) * 14
               WHEN 'umidade' THEN
                   62 - 14 * SIN((HOUR(q.lido_em) - 9) * PI() / 12) + (q.r - 0.5) * 5
               WHEN 'pressao' THEN
                   1013 + 5 * SIN(q.n * PI() / 60) + (q.r - 0.5) * 1.2
               WHEN 'uv' THEN
                   GREATEST(0, 9 * SIN((HOUR(q.lido_em) - 6) * PI() / 12)) + (q.r - 0.5) * 0.6
           END
       END, 2) AS valor,
       q.lido_em
FROM (
    SELECT s.id, s.tipo, s.status, h.n,
           NOW() - INTERVAL h.n HOUR AS lido_em,
           ABS(MOD(SIN(h.n * 12.9898 + s.id * 78.233) * 43758.5453, 1)) AS r
    FROM sensores s
    CROSS JOIN _horas h
) q
-- O sensor inativo parou de transmitir há 3 dias: só tem histórico antigo.
WHERE q.status = 'ativo' OR q.n >= 72;

DROP TEMPORARY TABLE _horas;

-- Corrige leituras negativas/irreais geradas pelo ruído (ex.: UV à noite).
UPDATE leitura_sensor SET valor = 0 WHERE id > 0 AND valor < 0; -- "id > 0" usa a chave primária: o modo seguro do Workbench (erro 1175) exige isso

-- ── Alertas ──────────────────────────────────────────────────────────────────
-- `resolvido_por = 1` é o administrador criado em schema.sql.
INSERT INTO alertas (sensor_id, severidade, mensagem, valor, resolvido, criado_em, resolvido_por) VALUES
    (1, 'critico', 'Temperatura acima do limite de 35 °C',               36.40,   0, NOW() - INTERVAL 6 HOUR,  NULL),
    (3, 'critico', 'Nível de ruído acima de 95 dB na prensa',            97.20,   0, NOW() - INTERVAL 3 HOUR,  NULL),
    (4, 'atencao', 'Qualidade do ar ruim no setor de soldagem (AQI>100)', 132.00, 0, NOW() - INTERVAL 11 HOUR, NULL),
    (5, 'atencao', 'Umidade abaixo do mínimo recomendado (35%)',          33.10,  0, NOW() - INTERVAL 20 HOUR, NULL),
    (6, 'info',    'Calibração programada do barômetro',                  NULL,   0, NOW() - INTERVAL 1 DAY,   NULL),
    (6, 'atencao', 'Queda rápida de pressão atmosférica',                1004.20, 1, NOW() - INTERVAL 2 DAY,   1),
    (2, 'info',    'Temperatura normalizada após acionar a ventilação',   24.80,  1, NOW() - INTERVAL 3 DAY,   1),
    (7, 'info',    'Sensor UV desativado para manutenção',                NULL,   1, NOW() - INTERVAL 3 DAY,   1),
    (1, 'critico', 'Temperatura acima do limite de 35 °C',               35.90,   1, NOW() - INTERVAL 4 DAY,   1);

-- ── Conferência (aparece no terminal ao executar via linha de comando) ───────
SELECT 'usuario' AS tabela, COUNT(*) AS registros FROM usuario
UNION ALL SELECT 'sensores', COUNT(*) FROM sensores
UNION ALL SELECT 'leitura_sensor', COUNT(*) FROM leitura_sensor
UNION ALL SELECT 'alertas', COUNT(*) FROM alertas;