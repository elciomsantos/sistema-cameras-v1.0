<?php

declare(strict_types=1);

/**
 * Migration: Indices de performance para full scans identificados no Relatorio_Performance.md (Tier 3 #18).
 *
 * - central_alarmes(ip), (mac), (numero_sei): busca por IP/MAC/SEI em api_alarmes.php
 * - equipamentos_anexos(created_at): ORDER BY em api_listar_anexos.php
 * - equipamentos_anexos(created_by): filtro por usuario
 *
 * Notas:
 * - ip_dvr nao foi indexado pois o relatorio cita como busca de baixa seletividade (rel 4).
 * - alarmes_manutencoes(os_status_id, updated_at) ja foi criado em 2026_07_11_100000.
 */

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("CREATE INDEX idx_alarmes_ip ON central_alarmes (ip)");
        $pdo->exec("CREATE INDEX idx_alarmes_mac ON central_alarmes (mac)");
        $pdo->exec("CREATE INDEX idx_alarmes_numero_sei ON central_alarmes (numero_sei)");

        $pdo->exec("CREATE INDEX idx_anexos_created_at ON equipamentos_anexos (created_at)");
        $pdo->exec("CREATE INDEX idx_anexos_created_by ON equipamentos_anexos (created_by)");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("DROP INDEX idx_anexos_created_by ON equipamentos_anexos");
        $pdo->exec("DROP INDEX idx_anexos_created_at ON equipamentos_anexos");

        $pdo->exec("DROP INDEX idx_alarmes_numero_sei ON central_alarmes");
        $pdo->exec("DROP INDEX idx_alarmes_mac ON central_alarmes");
        $pdo->exec("DROP INDEX idx_alarmes_ip ON central_alarmes");
    },
];
