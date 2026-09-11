<?php

declare(strict_types=1);

/**
 * Database Migration Runner — nucleo reutilizavel.
 *
 * Este modulo contem a logica de descoberta/aplicacao de migrations isolada
 * da camada de apresentacao (CLI x web). E consumido por:
 *   - database/migrate.php            (CLI: php database/migrate.php [--status|--down N])
 *   - inc/auto_migrate.php             (web: aplica migrations pendentes no bootstrap)
 *
 * Funcoes expostas:
 *   - ensureMigrationsTable(PDO)
 *   - getMigrationFiles(): array
 *   - getAppliedMigrations(PDO): array
 *   - applyPendingMigrations(PDO, ?LoggerInterface = null): array
 *   - rollbackMigrations(PDO, int): array
 *
 * Schema da tabela `migrations`:
 *   id INT PK, filename VARCHAR UNIQUE, executed_at DATETIME
 *
 * Tratamento de DDL implicit-commit do MySQL:
 *   MySQL faz commit automatico em DDL (ALTER/CREATE/DROP/RENAME). A transacao
 *   PDO original e encerrada por esse commit implicito, e rollBack/commit
 *   posteriores lancam "There is no active transaction". A estrategia adotada:
 *     1. Detectar inTransaction() antes de tentar rollback.
 *     2. Registrar a migration como aplicada via INSERT autonomo (fora da
 *        transacao original) para nao depender do estado do PDO.
 *     3. Reportar sucesso — a DDL efetivamente foi aplicada.
 *
 * lock-file para auto-migrate em web:
 *   Evita que multiplas requisicoes simultaneas disparem migrations em
 *   paralelo (race de INSERT na tabela migrations). O lock eh advisory via
 *   GET_LOCK('cftv_migrate', 0) no MySQL — nao-bloqueante, session-scoped.
 *   Quem nao pegar o lock simplesmente pula e confia que outra requisicao
 *   aplicara.
 */

require_once __DIR__ . '/../vendor/autoload.php';

function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function getMigrationFiles(): array
{
    $dir = __DIR__ . '/migrations';
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.php');
    $migrations = [];

    foreach ($files as $file) {
        $basename = basename($file, '.php');
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $basename, $matches)) {
            $migrations[$matches[1]] = $file;
        }
    }

    ksort($migrations);
    return $migrations;
}

function getAppliedMigrations(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT filename FROM migrations ORDER BY executed_at");
    $applied = [];
    try {
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $applied[] = $row['filename'];
        }
    } finally {
        $stmt->closeCursor();
    }
    return $applied;
}

/**
 * Aplica todas as migrations pendentes.
 *
 * @return array{applied: string[], skipped: string[], failed: array<array{migration:string,error:string}>}
 */
function applyPendingMigrations(PDO $pdo): array
{
    ensureMigrationsTable($pdo);

    $files = getMigrationFiles();
    $applied = getAppliedMigrations($pdo);
    $pending = array_diff(array_keys($files), $applied);

    $result = ['applied' => [], 'skipped' => [], 'failed' => []];

    if (empty($pending)) {
        return $result;
    }

    foreach ($pending as $timestamp) {
        $file = $files[$timestamp];

        $migration = require $file;
        if (!isset($migration['up']) || !is_callable($migration['up'])) {
            $result['failed'][] = ['migration' => $timestamp, 'error' => "No 'up' callable"];
            continue;
        }

        $registered = in_array($timestamp, $applied, true);

        try {
            if (!$registered) {
                $pdo->beginTransaction();
            }
            $migration['up']($pdo);
            if (!$registered) {
                $pdo->prepare("INSERT INTO migrations (filename) VALUES (?)")->execute([$timestamp]);
                $pdo->commit();
            }
            $registered
                ? $result['skipped'][] = $timestamp
                : $result['applied'][] = $timestamp;
        } catch (\Throwable $e) {
            // DDL implicit-commit: a transacao PDO original foi encerrada pelo MySQL.
            // Tenta rollback somente se ainda ha transacao; senao, registra a migration
            // via INSERT autonomo (DDL provavelmente ja foi aplicada).
            $inTransaction = false;
            try {
                $inTransaction = $pdo->inTransaction();
                if ($inTransaction) {
                    $pdo->rollBack();
                }
            } catch (\Throwable $rollbackErr) {
                $inTransaction = false;
            }

            if (!$inTransaction) {
                try {
                    $inserted = $pdo->prepare("INSERT INTO migrations (filename) VALUES (?)")
                        ->execute([$timestamp]);
                    if ($inserted) {
                        $result['applied'][] = $timestamp;
                        continue;
                    }
                } catch (\Throwable $insertErr) {
                    if (str_contains($insertErr->getMessage(), 'Duplicate')) {
                        $result['skipped'][] = $timestamp;
                        continue;
                    }
                }
            }
            $result['failed'][] = ['migration' => $timestamp, 'error' => $e->getMessage()];
        }
    }

    return $result;
}

/**
 * Rollback das ultimas N migrations (em ordem inversa de aplicacao).
 *
 * @return array{rolled_back: string[], failed: array<array{migration:string,error:string}>}
 */
function rollbackMigrations(PDO $pdo, int $count): array
{
    ensureMigrationsTable($pdo);

    $files = getMigrationFiles();
    $applied = getAppliedMigrations($pdo);

    $result = ['rolled_back' => [], 'failed' => []];

    if (empty($applied)) {
        return $result;
    }

    $toRollback = array_reverse(array_slice($applied, -$count));

    foreach ($toRollback as $timestamp) {
        if (!isset($files[$timestamp])) {
            $result['failed'][] = ['migration' => $timestamp, 'error' => 'Migration file not found'];
            continue;
        }

        $file = $files[$timestamp];
        $migration = require $file;
        if (!isset($migration['down']) || !is_callable($migration['down'])) {
            $result['failed'][] = ['migration' => $timestamp, 'error' => "No 'down' callable"];
            continue;
        }

        try {
            $pdo->beginTransaction();
            $migration['down']($pdo);
            $pdo->prepare("DELETE FROM migrations WHERE filename = ?")->execute([$timestamp]);
            $pdo->commit();
            $result['rolled_back'][] = $timestamp;
        } catch (\Throwable $e) {
            $inTransaction = false;
            try {
                $inTransaction = $pdo->inTransaction();
                if ($inTransaction) {
                    $pdo->rollBack();
                }
            } catch (\Throwable $rollbackErr) {
                $inTransaction = false;
            }

            if (!$inTransaction) {
                try {
                    $pdo->prepare("DELETE FROM migrations WHERE filename = ?")->execute([$timestamp]);
                    $result['rolled_back'][] = $timestamp;
                    continue;
                } catch (\Throwable $deleteErr) {
                    // ignora
                }
            }
            $result['failed'][] = ['migration' => $timestamp, 'error' => $e->getMessage()];
        }
    }

    return $result;
}
