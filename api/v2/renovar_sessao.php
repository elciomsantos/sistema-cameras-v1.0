<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $db = db();

    $usuarioId = currentUserId();
    $sessionToken = $_SESSION['session_token'] ?? null;

    $tempo_maximo = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
    $now = time();

    if (!isset($_SESSION['ultimo_acesso'])) {
        $_SESSION['ultimo_acesso'] = $now;
    }
    if (!isset($_SESSION['session_started_at'])) {
        $_SESSION['session_started_at'] = $now;
    }
    if (defined('SESSION_ABSOLUTE_TIMEOUT') && SESSION_ABSOLUTE_TIMEOUT > 0) {
        $elapsed = $now - (int)$_SESSION['session_started_at'];
        if ($elapsed >= SESSION_ABSOLUTE_TIMEOUT) {
            invalidateUniqueSession($db, $usuarioId, $sessionToken);
            clearLocalSessionState();
            ApiResponse::error('UNAUTHORIZED', 'Sessão expirada por tempo máximo.');
        }
    }

    $inatividade = $now - (int)$_SESSION['ultimo_acesso'];
    $time_left = $tempo_maximo - $inatividade;

    if ($time_left <= 0) {
        invalidateUniqueSession($db, $usuarioId, $sessionToken);
        clearLocalSessionState();
        ApiResponse::error('UNAUTHORIZED', 'Sessão expirada.');
    }

    $_SESSION['ultimo_acesso'] = $now;

    if (!isset($_SESSION['last_regenerate']) || ($now - (int)$_SESSION['last_regenerate']) >= 300) {
        session_regenerate_id(false);
        $_SESSION['last_regenerate'] = $now;

        // Apenas atualiza last_seen na sessao existente — NAO cria novo token,
        // pois isso causaria race condition com requisicoes concorrentes
        // (api_dashboard, etc.) que falhariam em enforceUniqueSession.
        try {
            $db->query(
                "UPDATE user_sessions SET last_seen = NOW(), updated_at = CURRENT_TIMESTAMP
                 WHERE usuario_id = :uid AND session_token_hash = :hash",
                [':uid' => $usuarioId, ':hash' => hashSessionToken($_SESSION['session_token'])]
            );
        } catch (Throwable $e) {
            error_log('[renovar_sessao] Erro ao atualizar last_seen: ' . $e->getMessage());
        }
    }

    $tokens = $_SESSION['csrf_tokens'] ?? [];
    $newCsrfToken = $tokens[array_key_last($tokens)] ?? null;

    ApiResponse::success([
        'message' => 'Sessão renovada',
        'time_left' => $tempo_maximo,
        'csrf_token' => $newCsrfToken,
    ]);

} catch (Throwable $e) {
    error_log('[renovar_sessao] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao renovar sessão.');
}
