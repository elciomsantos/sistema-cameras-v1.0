CREATE TABLE IF NOT EXISTS `notificacoes` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `tipo` VARCHAR(50) NOT NULL COMMENT 'Tipo: manutencao_atrasada, etc.',
    `titulo` VARCHAR(255) NOT NULL,
    `mensagem` TEXT NULL,
    `link` VARCHAR(500) NULL COMMENT 'URL para navegar ao contexto',
    `entidade_tipo` VARCHAR(50) NULL COMMENT 'Tipo da entidade (equipamento, alarme, local)',
    `entidade_id` BIGINT NULL COMMENT 'ID da entidade relacionada',
    `lida` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notificacoes_lida` (`lida`),
    KEY `idx_notificacoes_tipo` (`tipo`),
    KEY `idx_notificacoes_criadas` (`created_at` DESC),
    KEY `idx_notificacoes_lida_criadas` (`lida`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
