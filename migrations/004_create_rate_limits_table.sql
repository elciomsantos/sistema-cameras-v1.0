CREATE TABLE IF NOT EXISTS rate_limits (
    `key` VARCHAR(64) PRIMARY KEY COMMENT 'SHA-256 hash da chave de rate limit',
    tokens DOUBLE NOT NULL DEFAULT 0 COMMENT 'Tokens disponiveis (token bucket)',
    updated_at INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Timestamp Unix da ultima atualizacao',
    timestamps JSON NULL COMMENT 'Array de timestamps para sliding window'
) ENGINE=InnoDB;
