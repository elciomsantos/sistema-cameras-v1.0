<?php
/**
 * Migration runner for sistema-cameras
 * Executes pending migrations in order
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "=== Migration Runner ===\n\n";

$migrationsDir = __DIR__ . '/../migrations/';
$files = glob($migrationsDir . '*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

$db = db();
$pdo = $db->getConnection();

echo "Found " . count($files) . " migration files:\n";
foreach ($files as $f) {
    echo "  - " . basename($f) . "\n";
}
echo "\n";

// Check if migrations table exists
try {
    $pdo->exec("SELECT 1 FROM migrations LIMIT 1");
    echo "Migrations table exists.\n";
} catch (PDOException $e) {
    echo "Creating migrations table...\n";
    $pdo->exec("
        CREATE TABLE migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Migrations table created.\n";
}

$executedStmt = $pdo->prepare("SELECT filename FROM migrations");
$executedStmt->execute();
$executed = $executedStmt->fetchAll(PDO::FETCH_COLUMN);

$insertStmt = $pdo->prepare("INSERT INTO migrations (filename) VALUES (?)");

foreach ($files as $file) {
    $filename = basename($file);
    
    if (in_array($filename, $executed)) {
        echo "SKIP: $filename (already executed)\n";
        continue;
    }
    
    echo "RUNNING: $filename\n";
    
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "  ERROR: Could not read file\n";
        continue;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Split by semicolon, but be careful with semicolons in triggers/procedures
        // Simple approach: split by ; followed by newline and optional whitespace and not inside delimiter blocks
        $statements = [];
        $buffer = '';
        $inDelimiter = false;
        $delimiter = ';';
        
        $lines = explode("\n", $sql);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (strpos($trimmed, 'DELIMITER') === 0) {
                $parts = explode(' ', $trimmed, 2);
                if (count($parts) === 2) {
                    $delimiter = trim($parts[1]);
                    $inDelimiter = true;
                    continue;
                }
            }
            
            if ($inDelimiter && $trimmed === $delimiter) {
                $inDelimiter = false;
                $delimiter = ';';
                $statements[] = trim($buffer);
                $buffer = '';
                continue;
            }
            
            $buffer .= $line . "\n";
            
            if (!$inDelimiter && substr(rtrim($line), -1) === ';') {
                $statements[] = trim($buffer);
                $buffer = '';
            }
        }
        
        if (!empty($buffer)) {
            $statements[] = trim($buffer);
        }
        
        foreach ($statements as $stmt) {
            if (empty($stmt) || substr(ltrim($stmt), 0, 2) === '--') {
                continue;
            }
            $pdo->exec($stmt);
        }
        
        $insertStmt->execute([$filename]);
        $pdo->commit();
        echo "  OK\n";
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "  ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n=== All migrations completed ===\n";