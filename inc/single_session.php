<?php

require_once __DIR__ . '/../config/database.php';

function getSessionClientIp(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = resolveClientIp();
    return $cached;
}

function getSessionUserAgent(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ua = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', $ua);
    $ua = trim(mb_substr($ua, 0, 255));
    $cached = $ua !== '' ? $ua : 'unknown';
    return $cached;
}

function hashSessionToken(string $token): string
{
    return hash('sha256', $token); // Mantido SHA-256 para tokens de sessão (não senhas)
}

function ensureUserSessionsTable(database $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $result = $db->query("SELECT 1 FROM user_sessions LIMIT 1");
    if (($result['status'] ?? '') !== 'success') {
        throw new RuntimeException('Tabela user_sessions indisponivel. Execute a migracao de banco.');
    }

    $checked = true;
}

function createSessionToken(): string
{
    return bin2hex(random_bytes(32));
}

function registerUniqueSession(database $db, int $usuarioId, string $token): void
{
    ensureUserSessionsTable($db);

    if (function_exists('apcu_delete')) {
        apcu_delete("session_valid:{$usuarioId}");
    }

    $pdo = $db->getConnection();
    $lockName = 'user_session_' . $usuarioId;
    $lockAcquired = false;

    try {
        // Acquire application-level lock to prevent race conditions
        $stmt = $pdo->prepare("SELECT GET_LOCK(:lock_name, 10)");
        $stmt->bindValue(':lock_name', $lockName, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        if (!$row || (int)$row[0] !== 1) {
            throw new RuntimeException('Não foi possível obter lock de sessão.');
        }
        $lockAcquired = true;

        $db->query(
            "UPDATE user_sessions SET active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE usuario_id = :usuario_id AND active = 1",
            [':usuario_id' => $usuarioId]
        );

        $db->query(
            "INSERT INTO user_sessions (usuario_id, session_token_hash, session_id, ip_address, user_agent, active, last_seen)
             VALUES (:usuario_id, :session_token_hash, :session_id, :ip_address, :user_agent, 1, NOW())
             ON DUPLICATE KEY UPDATE
                session_token_hash = VALUES(session_token_hash),
                session_id = VALUES(session_id),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                active = 1,
                last_seen = NOW()",
            [
                ':usuario_id' => $usuarioId,
                ':session_token_hash' => hashSessionToken($token),
                ':session_id' => session_id(),
                ':ip_address' => getSessionClientIp(),
                ':user_agent' => getSessionUserAgent(),
            ]
        );

    } catch (Throwable $e) {
        throw $e;
    } finally {
        if ($lockAcquired) {
            $db->query("SELECT RELEASE_LOCK(:lock_name)", ['lock_name' => $lockName]);
        }
    }
}

function invalidateUniqueSession(database $db, int $usuarioId, ?string $token = null): void
{
    ensureUserSessionsTable($db);

    if (function_exists('apcu_delete')) {
        apcu_delete("session_valid:{$usuarioId}");
    }

    if ($token) {
        $db->query(
            "UPDATE user_sessions
             SET active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE usuario_id = :usuario_id
               AND session_token_hash = :session_token_hash",
            [
                ':usuario_id' => $usuarioId,
                ':session_token_hash' => hashSessionToken($token),
            ]
        );
        return;
    }

    $db->query(
        "UPDATE user_sessions
         SET active = 0, updated_at = CURRENT_TIMESTAMP
         WHERE usuario_id = :usuario_id",
        [':usuario_id' => $usuarioId]
    );
}

function isUniqueSessionValid(database $db, int $usuarioId, ?string $token): bool
{
    if (!$token) {
        return false;
    }

    ensureUserSessionsTable($db);

    $cacheKey = "session_valid:{$usuarioId}:" . hash('xxh3', $token);
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cacheKey);
        if ($cached !== false) {
            return (bool)$cached;
        }
    }

    $result = $db->query(
        "SELECT session_token_hash, session_id, active, last_seen, ip_address, user_agent
         FROM user_sessions
         WHERE usuario_id = :usuario_id
           AND active = 1
         LIMIT 1",
        [':usuario_id' => $usuarioId]
    );

    if (($result['status'] ?? '') !== 'success' || empty($result['data'])) {
        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, false, 5);
        }
        return false;
    }

    $row = $result['data'][0];
    if ((int)($row->active ?? 0) !== 1) {
        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, false, 5);
        }
        return false;
    }

    if (!hash_equals((string)$row->session_token_hash, hashSessionToken($token))) {
        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, false, 5);
        }
        return false;
    }

    $storedIp = (string)$row->ip_address;
    $currentIp = getSessionClientIp();
    if ($storedIp !== $currentIp && $storedIp !== '') {
        $normalizedStored = $storedIp === '::1' ? '127.0.0.1' : $storedIp;
        $normalizedCurrent = $currentIp === '::1' ? '127.0.0.1' : $currentIp;
        if ($normalizedStored !== $normalizedCurrent) {
            logWarning('IP mismatch (session not invalidated)', [
                'usuario_id' => $usuarioId,
                'stored_ip' => $storedIp,
                'current_ip' => $currentIp,
            ], 'security');
        }
    }

    if ((string)$row->user_agent !== getSessionUserAgent()) {
        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, false, 5);
        }
        return false;
    }

    // Atualizar last_seen apenas a cada 5 minutos para reduzir write amplification
    $lastSeen = $row->last_seen ?? null;
    $touchInterval = 300;
    $shouldTouch = $lastSeen === null || (time() - strtotime((string)$lastSeen)) > $touchInterval;

    if ($shouldTouch) {
        $db->query(
            "UPDATE user_sessions
             SET session_id = :session_id,
                 ip_address = :ip_address,
                 user_agent = :user_agent,
                 last_seen = NOW(),
                 updated_at = CURRENT_TIMESTAMP
             WHERE usuario_id = :usuario_id
               AND session_token_hash = :session_token_hash
               AND active = 1",
            [
                ':session_id' => session_id(),
                ':ip_address' => getSessionClientIp(),
                ':user_agent' => getSessionUserAgent(),
                ':usuario_id' => $usuarioId,
                ':session_token_hash' => hashSessionToken($token),
            ]
        );
    }

    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, true, 5);
    }

    return true;
}

function clearLocalSessionState(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
