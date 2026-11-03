USE banco_tcc;

SET @akron_safe_updates_anterior = @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

START TRANSACTION;

DELETE FROM alertas;
DELETE FROM leitura_sensor;
DELETE FROM sensores;
DELETE FROM usuario_estoque;
DELETE FROM estoques;
DELETE FROM auditoria;

COMMIT;

SET SESSION SQL_SAFE_UPDATES = @akron_safe_updates_anterior;