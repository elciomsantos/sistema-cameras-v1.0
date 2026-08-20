<?php

declare(strict_types=1);

/**
 * Migration: Relatório Diário de Turno (escala 12x36).
 *
 * Cria duas tabelas:
 *   - relatorios_turno:  cabeçalho do relatório (data, turno, operador, resumo,
 *                        situação geral e passagem para o próximo turno).
 *   - relatorio_turno_registros: acontecimentos registrados durante o turno
 *                        (tipo, título, descrição, situação, prioridade,
 *                        equipamento relacionado e referência opcional a uma O.S.).
 *
 * A O.S. é referenciada de forma polimórfica: existe em duas tabelas distintas
 * (equipamentos_manutencoes e alarmes_manutencoes). Por isso armazenamos
 * os_tipo ('camera'|'alarme') + os_id, sem FK, evitando duplicar dados.
 *
 * Não cria nem duplica usuários/equipamentos/alarmes/O.S. — apenas relaciona.
 * Histórico é permanente (não há soft-delete nem purge automático).
 */

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS relatorios_turno (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                data DATE NOT NULL,
                turno VARCHAR(20) NOT NULL COMMENT 'DIURNO ou NOTURNO',
                operador_id INT NOT NULL,
                hora_inicio TIME NULL,
                hora_fim TIME NULL,
                situacao_geral TEXT NULL COMMENT 'Situação geral do turno',
                passagem_turno TEXT NULL COMMENT 'Informações para o próximo turno',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_relatorio_turno (data, turno),
                CONSTRAINT fk_rel_turno_operador FOREIGN KEY (operador_id)
                    REFERENCES usuarios(id) ON DELETE RESTRICT,
                INDEX idx_rel_turno_data (data)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Relatório Diário de Turno (escala 12x36)'
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS relatorio_turno_registros (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                relatorio_id BIGINT NOT NULL,
                horario TIME NOT NULL COMMENT 'Horário do acontecimento',
                tipo VARCHAR(30) NOT NULL COMMENT 'Ocorrência, Alarme, Câmera, Sistema, Rede, Observação, Outros',
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT NULL,
                situacao VARCHAR(30) NOT NULL DEFAULT 'Pendente'
                    COMMENT 'Resolvido, Pendente, Em acompanhamento',
                prioridade VARCHAR(30) NOT NULL DEFAULT 'Normal'
                    COMMENT 'Normal, Atenção, Urgente',
                equipamento_id BIGINT UNSIGNED NULL COMMENT 'Equipamento relacionado, quando aplicável',
                os_tipo VARCHAR(20) NULL COMMENT 'camera|alarme — em qual tabela está a O.S.',
                os_id BIGINT NULL COMMENT 'ID da O.S. em equipamentos_manutencoes ou alarmes_manutencoes',
                numero_os VARCHAR(50) NULL COMMENT 'Número da O.S. para exibição',
                observacao TEXT NULL,
                created_by INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_rel_reg_relatorio FOREIGN KEY (relatorio_id)
                    REFERENCES relatorios_turno(id) ON DELETE CASCADE,
                CONSTRAINT fk_rel_reg_equip FOREIGN KEY (equipamento_id)
                    REFERENCES equipamentos(id) ON DELETE SET NULL,
                CONSTRAINT fk_rel_reg_user FOREIGN KEY (created_by)
                    REFERENCES usuarios(id) ON DELETE RESTRICT,
                INDEX idx_rel_reg_relatorio_situacao (relatorio_id, situacao),
                INDEX idx_rel_reg_equip (equipamento_id),
                INDEX idx_rel_reg_os (os_tipo, os_id),
                INDEX idx_rel_reg_created_by (created_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Acontecimentos registrados no Relatório Diário de Turno'
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS relatorio_turno_registros');
        $pdo->exec('DROP TABLE IF EXISTS relatorios_turno');
    },
];