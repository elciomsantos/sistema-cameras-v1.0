<?php

declare(strict_types=1);

/**
 * Auto-migrate no bootstrap web/API.
 *
 * Aplica migrations pendentes automaticamente a cada requisicao, eliminando o
 * risco de esquecer `php database/migrate.php` apos um deploy. Projeta a logica
 * reutilizavel de database/runner.php.
 *
 * Gate (desativacao opcional em producao via ENV):
 *   - CAMERAS_AUTO_MIGRATE=0  -> desativa completamente o auto-runner.
 *   - Padrao (ausente ou !=0)  -> ativo.
 *
 * Concorrencia:
 *   Usa GET_LOCK('cftv_migrate', 0) do MySQL — lock advisory, nao-bloqueante,
 *   session-scoped. A primeira requisicao que pega o lock executa as migrations
 *   pendentes; as demais requisicoes simultaneas pulam (nao bloqueiam/aguardam).
 *   Isso evita N requisicoes disparando migrations em paralelo e colidindo no
 *   INSERT da tabela `migrations` (UNIQUE em filename).
 *
 * Robustez:
 *   - Se o runner lancar excecao, ela eh registrada mas NAO propaga, para nao
 *     derrubar a requisicao/usuario. A migration pode ser aplicada em retry
 *     futuro (ou diagnosticada via `php database/migrate.php --status`).
 *   - DDL com implicit-commit do MySQL ja eh tratado pelo runner.
 *
 * Performance:
 *   - Sempre chama ensureMigrationsTable() (CREATE TABLE IF NOT EXISTS) e
 *     getAppliedMigrations() — query trivial sobre poucas linhas.
 *   - Sem pending => retorna imediatamente, custo approx 1-2 queries.
 */

require_once __DIR__ . '/../database/runner.php';

function runAutoMigrate(): void
{
    if (!defined('MIGRATE_LOCK_NAME')) {
        define('MIGRATE_LOCK_NAME', 'cftv_migrate');
    }

    if (getenv('CAMERAS_AUTO_MIGRATE') === '0') {
        return;
    }

    $db = db();
    $pdo = $db->getConnection();

    try {
        ensureMigrationsTable($pdo);
    } catch (\Throwable $e) {
        logError('auto_migrate: falha ao garantir tabela de migrations', ['error' => $e->getMessage()], 'migrate');
        return;
    }

    try {
        $lockStmt = $pdo->prepare("SELECT GET_LOCK(?, 0)");
        $lockStmt->execute([MIGRATE_LOCK_NAME]);
        $gotLock = (int)$lockStmt->fetchColumn();
    } catch (\Throwable $e) {
        logWarning('auto_migrate: GET_LOCK indisponivel, executando sem lock', ['error' => $e->getMessage()], 'migrate');
        $gotLock = 1;
    }

    if ($gotLock !== 1) {
        return;
    }

    try {
        $result = applyPendingMigrations($pdo);

        if (!empty($result['applied'])) {
            logInfo('auto_migrate: migrations aplicadas', ['applied' => $result['applied']], 'migrate');
        }
        foreach ($result['failed'] as $fail) {
            logError('auto_migrate: migration falhou', ['migration' => $fail['migration'], 'error' => $fail['error']], 'migrate');
        }
    } catch (\Throwable $e) {
        logError('auto_migrate: excecao nao tratada no runner', ['error' => $e->getMessage()], 'migrate');
    } finally {
        try {
            $pdo->exec("SELECT RELEASE_LOCK('" . MIGRATE_LOCK_NAME . "')");
        } catch (\Throwable $releaseErr) {
            // lock sera liberado no fim da session PDO de qualquer forma.
        }
    }
}
