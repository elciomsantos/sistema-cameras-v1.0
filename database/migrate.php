<?php

declare(strict_types=1);

/**
 * Database Migration Runner.
 *
 * Usage:
 *   php database/migrate.php              # Run all pending migrations
 *   php database/migrate.php --status     # Show migration status
 *   php database/migrate.php --down 1     # Rollback last 1 migration
 *   php database/migrate.php --down 2     # Rollback last 2 migrations
 *
 * Migration files must be in database/migrations/ with format:
 *   YYYY_MM_DD_HHMMSS_description.php
 *
 * Each file returns an array with 'up' and 'down' callables:
 *   return ['up' => function(PDO $pdo) { ... }, 'down' => function(PDO $pdo) { ... }];
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();
$pdo = $db->getConnection();

// Ensure migrations table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

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
        // Extract timestamp from filename (YYYY_MM_DD_HHMMSS)
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $basename, $matches)) {
            $migrations[$matches[1]] = $file;
        }
    }

    ksort($migrations);
    return $migrations;
}

function getAppliedMigrations(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY applied_at");
    $applied = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $applied[] = $row['migration'];
    }
    return $applied;
}

function runMigrations(PDO $pdo): void
{
    $files = getMigrationFiles();
    $applied = getAppliedMigrations($pdo);
    $pending = array_diff(array_keys($files), $applied);

    if (empty($pending)) {
        echo "No pending migrations.\n";
        return;
    }

    foreach ($pending as $timestamp) {
        $file = $files[$timestamp];
        echo "Migrating: " . basename($file, '.php') . "... ";

        $migration = require $file;
        if (!isset($migration['up']) || !is_callable($migration['up'])) {
            echo "ERROR: No 'up' callable\n";
            continue;
        }

        try {
            $pdo->beginTransaction();
            $migration['up']($pdo);
            $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)")->execute([$timestamp]);
            $pdo->commit();
            echo "OK\n";
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo "FAILED: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "All migrations applied successfully.\n";
}

function rollbackMigrations(PDO $pdo, int $count): void
{
    $files = getMigrationFiles();
    $applied = getAppliedMigrations($pdo);

    if (empty($applied)) {
        echo "No migrations to rollback.\n";
        return;
    }

    $toRollback = array_reverse(array_slice($applied, -$count));

    foreach ($toRollback as $timestamp) {
        if (!isset($files[$timestamp])) {
            echo "WARNING: Migration file not found for {$timestamp}, skipping.\n";
            continue;
        }

        $file = $files[$timestamp];
        echo "Rolling back: " . basename($file, '.php') . "... ";

        $migration = require $file;
        if (!isset($migration['down']) || !is_callable($migration['down'])) {
            echo "ERROR: No 'down' callable\n";
            continue;
        }

        try {
            $pdo->beginTransaction();
            $migration['down']($pdo);
            $pdo->prepare("DELETE FROM migrations WHERE migration = ?")->execute([$timestamp]);
            $pdo->commit();
            echo "OK\n";
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo "FAILED: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "Rolled back {$count} migration(s).\n";
}

function showStatus(PDO $pdo): void
{
    $files = getMigrationFiles();
    $applied = getAppliedMigrations($pdo);

    echo "Migration Status:\n";
    echo str_repeat('-', 60) . "\n";

    foreach ($files as $timestamp => $file) {
        $name = basename($file, '.php');
        $status = in_array($timestamp, $applied) ? '[APPLIED]' : '[PENDING]';
        echo "{$status} {$name}\n";
    }

    $pendingCount = count(array_diff(array_keys($files), $applied));
    echo str_repeat('-', 60) . "\n";
    echo "Total: " . count($files) . " | Applied: " . count($applied) . " | Pending: {$pendingCount}\n";
}

// Parse arguments
$args = array_slice($argv, 1);

if (in_array('--status', $args)) {
    showStatus($pdo);
} elseif (in_array('--down', $args)) {
    $downIndex = array_search('--down', $args);
    $count = isset($args[$downIndex + 1]) ? max(1, (int)$args[$downIndex + 1]) : 1;
    rollbackMigrations($pdo, $count);
} else {
    runMigrations($pdo);
}
