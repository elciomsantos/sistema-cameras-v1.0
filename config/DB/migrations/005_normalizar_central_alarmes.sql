-- Migration 005: Normalizar central_alarmes — FK para status e regioes
--
-- Uso: mysql -u root -p cftv_gml < config/DB/migrations/005_normalizar_central_alarmes.sql
--
-- Idempotente: pode ser executado múltiplas vezes com segurança.

-- 1. Garantir que status existem (alarmes usam valores diferentes de equipamentos)
INSERT IGNORE INTO status (nome) VALUES ('ATIVO'), ('INATIVO'), ('DESLIGADO'), ('PENDENTE');

-- 2. Garantir que regioes existem
INSERT IGNORE INTO regioes (nome) VALUES
    ('CENTRO'), ('LESTE'), ('OESTE'), ('SUL'), ('NORTE'), ('DISTRITO');

-- 3. Adicionar colunas FK (se não existirem)
SET @exists_status_id = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'central_alarmes' AND COLUMN_NAME = 'status_id'
);
SET @exists_regiao_id = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'central_alarmes' AND COLUMN_NAME = 'regiao_id'
);

SET @sql1 = IF(@exists_status_id = 0,
    'ALTER TABLE central_alarmes ADD COLUMN status_id INT NULL AFTER `status`',
    'SELECT 1');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql2 = IF(@exists_regiao_id = 0,
    'ALTER TABLE central_alarmes ADD COLUMN regiao_id INT NULL AFTER `regiao`',
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 4. Migrar dados existentes: status_id
UPDATE central_alarmes a
LEFT JOIN status s ON
    CASE
        WHEN a.status = 'Ativo'       THEN s.nome = 'ATIVO'
        WHEN a.status = 'Inativo'     THEN s.nome = 'INATIVO'
        WHEN a.status = 'Desligado'   THEN s.nome = 'DESLIGADO'
        WHEN a.status = 'Pendente'    THEN s.nome = 'PENDENTE'
        WHEN a.status = 'Manutenção'  THEN s.nome = 'MANUTENCAO'
        WHEN a.status = 'Manutencao'  THEN s.nome = 'MANUTENCAO'
        WHEN a.status = 'funcionando' THEN s.nome = 'FUNCIONANDO'
        WHEN a.status = 'parada'      THEN s.nome = '  PARADA'
        WHEN a.status = 'desativado'  THEN s.nome = 'DESATICADO'
        ELSE s.nome = UPPER(TRIM(a.status))
    END
SET a.status_id = s.id
WHERE a.status_id IS NULL
  AND a.status IS NOT NULL
  AND a.status != '';

-- 5. Migrar dados existentes: regiao_id
UPDATE central_alarmes a
JOIN regioes r ON UPPER(TRIM(a.regiao)) = r.nome
SET a.regiao_id = r.id
WHERE a.regiao_id IS NULL
  AND a.regiao IS NOT NULL
  AND a.regiao != '';

-- 6. Adicionar FKs (se não existirem)
SET @fk_status_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'central_alarmes'
      AND CONSTRAINT_NAME = 'fk_central_alarmes_status'
);
SET @fk_regiao_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'central_alarmes'
      AND CONSTRAINT_NAME = 'fk_central_alarmes_regiao'
);

SET @sql3 = IF(@fk_status_exists = 0,
    'ALTER TABLE central_alarmes ADD CONSTRAINT fk_central_alarmes_status
     FOREIGN KEY (status_id) REFERENCES status(id) ON DELETE RESTRICT',
    'SELECT 1');
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @sql4 = IF(@fk_regiao_exists = 0,
    'ALTER TABLE central_alarmes ADD CONSTRAINT fk_central_alarmes_regiao
     FOREIGN KEY (regiao_id) REFERENCES regioes(id) ON DELETE RESTRICT',
    'SELECT 1');
PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- 7. Índices (com verificação via information_schema)
SET @idx_status_id = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'central_alarmes'
      AND INDEX_NAME = 'idx_alarmes_status_id'
);
SET @sql5 = IF(@idx_status_id = 0,
    'CREATE INDEX idx_alarmes_status_id ON central_alarmes(status_id)',
    'SELECT 1');
PREPARE stmt5 FROM @sql5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

SET @idx_regiao_id = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'central_alarmes'
      AND INDEX_NAME = 'idx_alarmes_regiao_id'
);
SET @sql6 = IF(@idx_regiao_id = 0,
    'CREATE INDEX idx_alarmes_regiao_id ON central_alarmes(regiao_id)',
    'SELECT 1');
PREPARE stmt6 FROM @sql6;
EXECUTE stmt6;
DEALLOCATE PREPARE stmt6;
