-- Migration 006: Adicionar FKs em equipamentos_anexos
--
-- Corrige tipo das colunas (remove UNSIGNED) e adiciona FKs:
--   manutencao_camera_id → equipamentos_manutencoes(id) ON DELETE SET NULL
--   manutencao_alarme_id → alarmes_manutencoes(id)   ON DELETE SET NULL
--
-- Uso: mysql -u root -p cftv_gml < config/DB/migrations/006_fk_equipamentos_anexos.sql
--
-- Idempotente: pode ser executado múltiplas vezes com segurança.

-- 1. Remover FKs existentes (se houver) para permitir MODIFY COLUMN
SET @sql_drop1 = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND CONSTRAINT_NAME = 'fk_anexos_manut_camera') > 0,
    'ALTER TABLE equipamentos_anexos DROP FOREIGN KEY fk_anexos_manut_camera',
    'SELECT 1');
PREPARE stmt_drop1 FROM @sql_drop1;
EXECUTE stmt_drop1;
DEALLOCATE PREPARE stmt_drop1;

SET @sql_drop2 = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND CONSTRAINT_NAME = 'fk_anexos_manut_alarme') > 0,
    'ALTER TABLE equipamentos_anexos DROP FOREIGN KEY fk_anexos_manut_alarme',
    'SELECT 1');
PREPARE stmt_drop2 FROM @sql_drop2;
EXECUTE stmt_drop2;
DEALLOCATE PREPARE stmt_drop2;

-- 2. Remover INDEX antigos (se existirem do CREATE TABLE original)
SET @sql_drop_idx1 = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND INDEX_NAME = 'idx_anexos_manut_camera') > 0,
    'ALTER TABLE equipamentos_anexos DROP INDEX idx_anexos_manut_camera',
    'SELECT 1');
PREPARE stmt_drop_idx1 FROM @sql_drop_idx1;
EXECUTE stmt_drop_idx1;
DEALLOCATE PREPARE stmt_drop_idx1;

SET @sql_drop_idx2 = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND INDEX_NAME = 'idx_anexos_manut_alarme') > 0,
    'ALTER TABLE equipamentos_anexos DROP INDEX idx_anexos_manut_alarme',
    'SELECT 1');
PREPARE stmt_drop_idx2 FROM @sql_drop_idx2;
EXECUTE stmt_drop_idx2;
DEALLOCATE PREPARE stmt_drop_idx2;

-- 3. Corrigir tipo das colunas (remover UNSIGNED)
SET @sql_col1 = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND COLUMN_NAME = 'manutencao_camera_id' AND COLUMN_TYPE = 'bigint unsigned') > 0,
    'ALTER TABLE equipamentos_anexos MODIFY COLUMN manutencao_camera_id BIGINT NULL',
    'SELECT 1');
PREPARE stmt_col1 FROM @sql_col1;
EXECUTE stmt_col1;
DEALLOCATE PREPARE stmt_col1;

SET @sql_col2 = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND COLUMN_NAME = 'manutencao_alarme_id' AND COLUMN_TYPE = 'bigint unsigned') > 0,
    'ALTER TABLE equipamentos_anexos MODIFY COLUMN manutencao_alarme_id BIGINT NULL',
    'SELECT 1');
PREPARE stmt_col2 FROM @sql_col2;
EXECUTE stmt_col2;
DEALLOCATE PREPARE stmt_col2;

-- 4. Criar INDEX + FK para manutencao_camera_id
SET @sql_add1 = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND CONSTRAINT_NAME = 'fk_anexos_manut_camera') = 0,
    'ALTER TABLE equipamentos_anexos ADD INDEX idx_anexos_manut_camera (manutencao_camera_id), ADD CONSTRAINT fk_anexos_manut_camera FOREIGN KEY (manutencao_camera_id) REFERENCES equipamentos_manutencoes(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt_add1 FROM @sql_add1;
EXECUTE stmt_add1;
DEALLOCATE PREPARE stmt_add1;

-- 5. Criar INDEX + FK para manutencao_alarme_id
SET @sql_add2 = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos' AND CONSTRAINT_NAME = 'fk_anexos_manut_alarme') = 0,
    'ALTER TABLE equipamentos_anexos ADD INDEX idx_anexos_manut_alarme (manutencao_alarme_id), ADD CONSTRAINT fk_anexos_manut_alarme FOREIGN KEY (manutencao_alarme_id) REFERENCES alarmes_manutencoes(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt_add2 FROM @sql_add2;
EXECUTE stmt_add2;
DEALLOCATE PREPARE stmt_add2;
