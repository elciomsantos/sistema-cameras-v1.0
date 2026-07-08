ALTER TABLE locais
    ADD COLUMN deleted_at DATETIME NULL COMMENT 'Data de exclusao logica (soft delete)' AFTER updated_at;

CREATE INDEX idx_locais_deleted ON locais(deleted_at);
