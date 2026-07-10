<?php

declare(strict_types=1);

/**
 * Migration: Adiciona coluna thumbnails na tabela equipamentos_anexos.
 */

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE equipamentos_anexos ADD COLUMN thumbnails JSON NULL COMMENT 'Caminhos dos thumbnails gerados {\"small\":\"...\",\"medium\":\"...\"}' AFTER conteudo_comprimido");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE equipamentos_anexos DROP COLUMN thumbnails");
    },
];
