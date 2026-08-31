<?php

declare(strict_types=1);

/**
 * Migration: Anotações pessoais do usuário (bloco de notas privado).
 *
 * Cria a tabela `usuario_anotacoes` — anotações exclusivas de cada usuário
 * logado, independentes de data/turno. Apenas o dono (usuario_id) tem acesso.
 * Modelo de tarefa com ciclo de vida: pendente -> em_andamento -> realizada,
 * com possibilidade de arquivar (arquivada=1).
 */

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuario_anotacoes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL COMMENT 'Dono da anotação (apenas ele vê)',
                titulo VARCHAR(150) NOT NULL,
                texto TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pendente'
                    COMMENT 'pendente, em_andamento, realizada',
                arquivada TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=ativa, 1=arquivada',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_usuario_anotacao_user FOREIGN KEY (usuario_id)
                    REFERENCES usuarios(id) ON DELETE CASCADE,
                INDEX idx_anotacao_usuario_status (usuario_id, status),
                INDEX idx_anotacao_usuario_arquivada (usuario_id, arquivada),
                INDEX idx_anotacao_usuario_created (usuario_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Anotações pessoais privadas do usuário'
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS usuario_anotacoes');
    },
];
