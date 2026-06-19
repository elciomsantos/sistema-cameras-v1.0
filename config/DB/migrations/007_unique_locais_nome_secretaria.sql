-- Migration 007: UNIQUE em locais(nome, secretaria_id)
--
-- Adiciona UNIQUE (nome, secretaria_id) para evitar locais duplicados
-- dentro da mesma secretaria.
--
-- Uso: mysql -u root -p cftv_gml < config/DB/migrations/007_unique_locais_nome_secretaria.sql
--
-- Idempotente: pode ser executado múltiplas vezes com segurança.

-- 1. Remover duplicatas existentes (manter o registro mais antigo)
DROP TEMPORARY TABLE IF EXISTS tmp_locais_dedup;
CREATE TEMPORARY TABLE tmp_locais_dedup AS
SELECT l1.id AS duplicate_id, l2.min_id AS keep_id
FROM locais l1
JOIN (
    SELECT nome, secretaria_id, MIN(id) AS min_id
    FROM locais
    GROUP BY nome, secretaria_id
    HAVING COUNT(*) > 1
) l2 ON l1.nome = l2.nome
   AND (l1.secretaria_id = l2.secretaria_id OR (l1.secretaria_id IS NULL AND l2.secretaria_id IS NULL))
   AND l1.id != l2.min_id;

-- Reapontar equipamentos para o registro mantido
UPDATE equipamentos e
JOIN tmp_locais_dedup t ON e.local_id = t.duplicate_id
SET e.local_id = t.keep_id;

-- Remover duplicatas
DELETE l FROM locais l
JOIN tmp_locais_dedup t ON l.id = t.duplicate_id;

DROP TEMPORARY TABLE IF EXISTS tmp_locais_dedup;

-- 2. Adicionar UNIQUE (se não existir)
SET @uq_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'locais'
      AND CONSTRAINT_NAME = 'uq_locais_nome_secretaria'
);
SET @sql = IF(@uq_exists = 0,
    'ALTER TABLE locais ADD CONSTRAINT uq_locais_nome_secretaria UNIQUE (nome, secretaria_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
