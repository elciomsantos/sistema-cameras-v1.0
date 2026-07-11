<?php

declare(strict_types=1);

/**
 * Migration: Adiciona indice composto para ORDER BY updated_at com filtro os_status_id.
 * Reduz filesort em queries de manutencao de cameras.
 */

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("CREATE INDEX idx_eq_manut_os_status_updated ON equipamentos_manutencoes (os_status_id, updated_at)");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("DROP INDEX idx_eq_manut_os_status_updated ON equipamentos_manutencoes");
    },
];
