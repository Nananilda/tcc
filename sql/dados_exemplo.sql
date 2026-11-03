-- ÁKRON: demonstração opcional, sem apagar dados existentes.
-- Execute APÓS schema.sql e php sql/migrar.php; nunca necessário em produção.
-- Usuário comum: usuario / Usuario@123. Administrador: criado no schema.sql.
-- Insere apenas sensores/leituras de demonstração ainda ausentes.
SET NAMES utf8mb4;
SET time_zone = '-03:00';
USE banco_tcc;
INSERT INTO usuario(nome,login,senha,tipo,status) VALUES
('Usuário de demonstração','usuario','$2y$12$871oVLor.Rm0VqHxj5r3GuER3WdYnqqFTjI0UG3fg3prYY3I9Wql.','usuario','ativo')
ON DUPLICATE KEY UPDATE login=login;
INSERT INTO estoques(nome,descricao,localizacao,status)
SELECT 'Estoque de demonstração','Dados fictícios para testar o monitoramento.','Área de testes','ativo'
WHERE NOT EXISTS(SELECT 1 FROM estoques WHERE nome='Estoque de demonstração');
SET @estoque_demo=(SELECT MIN(id) FROM estoques WHERE nome='Estoque de demonstração');
INSERT IGNORE INTO usuario_estoque(usuario_id,estoque_id)
SELECT id,@estoque_demo FROM usuario WHERE login='usuario';
INSERT INTO sensores(nome,tipo,estoque_id,localizacao,status,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max,intervalo_comunicacao)
SELECT 'Temperatura demonstração','temperatura',@estoque_demo,'Prateleira A','ativo',NULL,30,NULL,35,300
WHERE NOT EXISTS(SELECT 1 FROM sensores WHERE estoque_id=@estoque_demo AND nome='Temperatura demonstração');
INSERT INTO sensores(nome,tipo,estoque_id,localizacao,status,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max,intervalo_comunicacao)
SELECT 'Umidade demonstração','umidade',@estoque_demo,'Prateleira A','ativo',35,80,20,90,300
WHERE NOT EXISTS(SELECT 1 FROM sensores WHERE estoque_id=@estoque_demo AND nome='Umidade demonstração');
INSERT INTO sensores(nome,tipo,estoque_id,localizacao,status,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max,intervalo_comunicacao)
SELECT 'Ar demonstração','qualidade_ar',@estoque_demo,'Prateleira A','ativo',NULL,100,NULL,150,300
WHERE NOT EXISTS(SELECT 1 FROM sensores WHERE estoque_id=@estoque_demo AND nome='Ar demonstração');
CREATE TEMPORARY TABLE _sensores_demo AS
SELECT s.* FROM sensores s WHERE s.estoque_id=@estoque_demo AND NOT EXISTS(SELECT 1 FROM leitura_sensor l WHERE l.sensor_id=s.id);
CREATE TEMPORARY TABLE _horas AS
SELECT a.n+10*b.n AS n FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2) b WHERE a.n+10*b.n<=24;
INSERT INTO leitura_sensor(sensor_id,estoque_id,valor,lido_em,situacao,limite_atencao_min,limite_atencao_max,limite_critico_min,limite_critico_max)
SELECT s.id,s.estoque_id,ROUND(CASE s.tipo WHEN 'temperatura' THEN 24+3*SIN(h.n/3) WHEN 'umidade' THEN 60+8*SIN(h.n/4) ELSE 50+10*SIN(h.n/4) END,2),NOW()-INTERVAL h.n HOUR,'normal',s.limite_atencao_min,s.limite_atencao_max,s.limite_critico_min,s.limite_critico_max
FROM _sensores_demo s CROSS JOIN _horas h;
UPDATE sensores s JOIN _sensores_demo d ON d.id=s.id SET s.ultima_comunicacao=NOW();
DROP TEMPORARY TABLE _sensores_demo;
DROP TEMPORARY TABLE _horas;
SELECT 'Demonstração criada sem excluir registros existentes.' AS resultado;
