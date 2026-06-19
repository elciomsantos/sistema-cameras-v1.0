-- Migration 006: Adicionar FKs faltantes em equipamentos_anexos
--
-- Adiciona constraints faltantes:
--   manutencao_camera_id → equipamentos_manutencoes(id) ON DELETE SET NULL
--   manutencao_alarme_id → alarmes_manutencoes(id)   ON DELETE SET NULL
--
-- Também corrige o tipo das colunas (removendo UNSIGNED) para
-- compatibilidade com as PKs das tabelas pai.
--
-- Uso: mysql -u root -p cftv_gml < config/DB/migrations/006_fk_equipamentos_anexos.sql
--
-- Idempotente: pode ser executado múltiplas vezes com segurança.

-- 1. Corrigir tipo das colunas (remover UNSIGNED para compatibilidade com PKs das tabelas pai)
SET @col_manut_camera = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos'
      AND COLUMN_NAME = 'manutencao_camera_id'
      AND COLUMN_TYPE = 'bigint unsigned'
);
SET @sql1 = IF(@col_manut_camera > 0,
    'ALTER TABLE equipamentos_anexos MODIFY COLUMN manutencao_camera_id BIGINT NULL',
    'SELECT 1');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @col_manut_alarme = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos'
      AND COLUMN_NAME = 'manutencao_alarme_id'
      AND COLUMN_TYPE = 'bigint unsigned'
);
SET @sql2 = IF(@col_manut_alarme > 0,
    'ALTER TABLE equipamentos_anexos MODIFY COLUMN manutencao_alarme_id BIGINT NULL',
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 2. Adicionar FKs (se não existirem)
SET @fk_manut_camera = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos'
      AND CONSTRAINT_NAME = 'fk_anexos_manut_camera'
);
SET @sql3 = IF(@fk_manut_camera = 0,
    'ALTER TABLE equipamentos_anexos
     ADD CONSTRAINT fk_anexos_manut_camera
     FOREIGN KEY (manutencao_camera_id) REFERENCES equipamentos_manutencoes(id)
     ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @fk_manut_alarme = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'equipamentos_anexos'
      AND CONSTRAINT_NAME = 'fk_anexos_manut_alarme'
);
SET @sql4 = IF(@fk_manut_alarme = 0,
    'ALTER TABLE equipamentos_anexos
     ADD CONSTRAINT fk_anexos_manut_alarme
     FOREIGN KEY (manutencao_alarme_id) REFERENCES alarmes_manutencoes(id)
     ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;
