<?php

declare(strict_types=1);

/**
 * Migration: Converte auditoria_eventos para tabela particionada por RANGE(COLUMN).
 *
 * Particionamento mensal por created_at, com DROP PARTITION O(1) em limpeza.
 *
 *-trade-offs:
 * - Remove FK constraints (aceitável para audit logs append-only)
 * - Remove generated columns (recriadas como virtuais na query ou na view)
 * - Requer ALTER TABLE (online DDL no MySQL 8.0 com ALGORITHM=INPLACE)
 */
return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Criar tabela particionada
        $pdo->exec("
            CREATE TABLE auditoria_eventos_partitioned (
                id BIGINT AUTO_INCREMENT,
                entidade VARCHAR(80) NOT NULL COMMENT 'Nome da tabela',
                entidade_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do registro',
                operacao_id TINYINT NOT NULL COMMENT '1=INSERT, 2=UPDATE, 3=DELETE',
                dados_antes JSON NULL COMMENT 'Estado anterior (para UPDATE/DELETE)',
                dados_depois JSON NULL COMMENT 'Novo estado (para INSERT/UPDATE)',
                origem VARCHAR(100) NULL COMMENT 'Origem da ação',
                changed_by INT NULL COMMENT 'ID do usuário',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id, created_at),
                INDEX idx_auditoria_entidade (entidade, created_at),
                INDEX idx_auditoria_operacao (operacao_id),
                INDEX idx_auditoria_usuario (changed_by),
                INDEX idx_auditoria_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Auditoria particionada por mês'
            PARTITION BY RANGE (TO_DAYS(created_at)) (
                PARTITION p2025_01 VALUES LESS THAN (TO_DAYS('2025-02-01')),
                PARTITION p2025_02 VALUES LESS THAN (TO_DAYS('2025-03-01')),
                PARTITION p2025_03 VALUES LESS THAN (TO_DAYS('2025-04-01')),
                PARTITION p2025_04 VALUES LESS THAN (TO_DAYS('2025-05-01')),
                PARTITION p2025_05 VALUES LESS THAN (TO_DAYS('2025-06-01')),
                PARTITION p2025_06 VALUES LESS THAN (TO_DAYS('2025-07-01')),
                PARTITION p2025_07 VALUES LESS THAN (TO_DAYS('2025-08-01')),
                PARTITION p2025_08 VALUES LESS THAN (TO_DAYS('2025-09-01')),
                PARTITION p2025_09 VALUES LESS THAN (TO_DAYS('2025-10-01')),
                PARTITION p2025_10 VALUES LESS THAN (TO_DAYS('2025-11-01')),
                PARTITION p2025_11 VALUES LESS THAN (TO_DAYS('2025-12-01')),
                PARTITION p2025_12 VALUES LESS THAN (TO_DAYS('2026-01-01')),
                PARTITION p2026_01 VALUES LESS THAN (TO_DAYS('2026-02-01')),
                PARTITION p2026_02 VALUES LESS THAN (TO_DAYS('2026-03-01')),
                PARTITION p2026_03 VALUES LESS THAN (TO_DAYS('2026-04-01')),
                PARTITION p2026_04 VALUES LESS THAN (TO_DAYS('2026-05-01')),
                PARTITION p2026_05 VALUES LESS THAN (TO_DAYS('2026-06-01')),
                PARTITION p2026_06 VALUES LESS THAN (TO_DAYS('2026-07-01')),
                PARTITION p2026_07 VALUES LESS THAN (TO_DAYS('2026-08-01')),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");

        // Copiar dados existentes
        $pdo->exec("
            INSERT IGNORE INTO auditoria_eventos_partitioned
                (id, entidade, entidade_id, operacao_id, dados_antes, dados_depois, origem, changed_by, created_at)
            SELECT
                id, entidade, entidade_id, operacao_id, dados_antes, dados_depois, origem, changed_by, created_at
            FROM auditoria_eventos
        ");

        // Renomear tabelas (swapping)
        $pdo->exec("RENAME TABLE auditoria_eventos TO auditoria_eventos_old, auditoria_eventos_partitioned TO auditoria_eventos");

        // Dropar tabela antiga (já migrada)
        $pdo->exec("DROP TABLE auditoria_eventos_old");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },

    'down' => function (PDO $pdo): void {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Recriar tabela original sem particionamento
        $pdo->exec("
            CREATE TABLE auditoria_eventos_original (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                entidade VARCHAR(80) NOT NULL COMMENT 'Nome da tabela',
                entidade_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do registro',
                operacao_id TINYINT NOT NULL COMMENT 'FK para tipos_operacao (1=INSERT, 2=UPDATE, 3=DELETE)',
                dados_antes JSON NULL COMMENT 'Estado anterior (para UPDATE/DELETE)',
                dados_depois JSON NULL COMMENT 'Novo estado (para INSERT/UPDATE)',
                origem VARCHAR(100) NULL COMMENT 'Origem da ação (IP, módulo, etc)',
                changed_by INT NULL COMMENT 'ID do usuário que realizou a ação',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_entidade_data (entidade, created_at),
                INDEX idx_audit_entidade_operacao_data (entidade, operacao_id, created_at),
                INDEX idx_audit_status (operacao_id),
                INDEX idx_audit_usuario (changed_by),
                INDEX idx_auditoria_created_at (created_at),
                CONSTRAINT fk_audit_operacao FOREIGN KEY (operacao_id) REFERENCES tipos_operacao(id) ON DELETE RESTRICT,
                CONSTRAINT fk_audit_user FOREIGN KEY (changed_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoria de alterações nas tabelas principais'
        ");

        // Copiar dados de volta
        $pdo->exec("
            INSERT INTO auditoria_eventos_original
                (id, entidade, entidade_id, operacao_id, dados_antes, dados_depois, origem, changed_by, created_at)
            SELECT
                id, entidade, entidade_id, operacao_id, dados_antes, dados_depois, origem, changed_by, created_at
            FROM auditoria_eventos
        ");

        // Swap
        $pdo->exec("RENAME TABLE auditoria_eventos TO auditoria_eventos_partitioned, auditoria_eventos_original TO auditoria_eventos");
        $pdo->exec("DROP TABLE auditoria_eventos_partitioned");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    },
];
