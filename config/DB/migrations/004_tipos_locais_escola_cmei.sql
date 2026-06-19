-- Migration 004: Adicionar tipos ESCOLA e CMEI em tipos_locais
-- e migrar registros existentes em locais
--
-- Uso: mysql -u root -p cftv_gml < config/DB/migrations/004_tipos_locais_escola_cmei.sql
--
-- Idempotente: pode ser executado múltiplas vezes com segurança.

SET @escola_id = (SELECT id FROM tipos_locais WHERE nome = 'ESCOLA' LIMIT 1);
SET @cmei_id = (SELECT id FROM tipos_locais WHERE nome = 'CMEI' LIMIT 1);

INSERT INTO tipos_locais (nome)
SELECT 'ESCOLA' WHERE @escola_id IS NULL;

INSERT INTO tipos_locais (nome)
SELECT 'CMEI' WHERE @cmei_id IS NULL;

-- Re-carregar IDs após insert
SET @escola_id = (SELECT id FROM tipos_locais WHERE nome = 'ESCOLA' LIMIT 1);
SET @cmei_id = (SELECT id FROM tipos_locais WHERE nome = 'CMEI' LIMIT 1);

-- Migrar registros existentes (apenas onde ainda não têm tipo_local_id)
UPDATE locais
SET tipo_local_id = @escola_id
WHERE (nome LIKE '%ESCOLA%' OR nome LIKE '%Escola%')
  AND tipo_local_id IS NULL;

UPDATE locais
SET tipo_local_id = @cmei_id
WHERE (nome LIKE '%CMEI%' OR nome LIKE '%Cmei%')
  AND tipo_local_id IS NULL;
