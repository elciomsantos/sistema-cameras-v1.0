<?php

function getRequestDatabase(): database
{
    return database::getInstance();
}

function sendTimeoutResponse(string $message, ?string $reason = null): never
{
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
    $payload = [
        'status' => 'expired',
        'success' => false,
        'error' => 'Sessao expirada',
        'message' => $message,
    ];
    if ($reason !== null) {
        $payload['reason'] = $reason;
    }
    echo json_encode($payload);
    exit;
}

function invalidateAndClearSession(): void
{
    $usuarioId = currentUserId();
    $sessionToken = $_SESSION['session_token'] ?? null;
    if ($usuarioId) {
        try {
            $db = getRequestDatabase();
            invalidateUniqueSession($db, $usuarioId, $sessionToken);
        } catch (Throwable $e) {
            logError('Erro ao invalidar sessao', ['error' => $e->getMessage()], 'session');
        }
    }
    clearLocalSessionState();
}

function finalizarSessaoTimeout(string $motivo, ?string $reason = null, bool $isApi = false): never
{
    invalidateAndClearSession();
    if ($isApi) {
        sendTimeoutResponse($motivo, $reason);
    }
    header('Location: index.php?page=login&timeout=1');
    exit;
}

function verificarTimeout($isApi = false, $tempo_maximo = SESSION_TIMEOUT)
{
    if (!isset($_SESSION['usuario'])) {
        return 0;
    }

    $now = time();
    if (!isset($_SESSION['session_started_at'])) {
        finalizarSessaoTimeout('Sessao invalida. Realize login novamente.', null, $isApi);
    }

    if (SESSION_ABSOLUTE_TIMEOUT > 0) {
        $elapsed = $now - (int)$_SESSION['session_started_at'];
        if ($elapsed >= SESSION_ABSOLUTE_TIMEOUT) {
            finalizarSessaoTimeout('Sessao encerrada por tempo maximo', 'absolute_timeout', $isApi);
        }
    }

    if (!isset($_SESSION['ultimo_acesso'])) {
        $_SESSION['ultimo_acesso'] = $now;
        return $tempo_maximo;
    }

    $inatividade = $now - $_SESSION['ultimo_acesso'];
    $time_left = $tempo_maximo - $inatividade;

    if ($inatividade >= $tempo_maximo) {
        finalizarSessaoTimeout('Sessao encerrada por inatividade', null, $isApi);
    }

    $_SESSION['ultimo_acesso'] = $now;

    return max(0, $time_left);
}

function enforceUniqueSession(bool $isApi = false): void
{
    if (!isset($_SESSION['usuario'])) {
        return;
    }

    $usuarioId = currentUserId();
    $sessionToken = $_SESSION['session_token'] ?? null;
    if (!$usuarioId || !$sessionToken) {
        clearLocalSessionState();
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'status' => 'expired',
                'reason' => 'concurrent_login',
                'error' => 'Sessao invalida. Realize login novamente.',
                'message' => 'Sessao invalida. Realize login novamente.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: index.php?page=login&concurrent=1');
        exit;
    }

    try {
        $db = getRequestDatabase();
        $valid = isUniqueSessionValid($db, $usuarioId, $sessionToken);
    } catch (Throwable $e) {
        logWarning('Erro ao validar sessao unica (fallback: rejeitando sessao)', ['error' => $e->getMessage()], 'session');
        $valid = false;
    }

    if ($valid) {
        return;
    }

    clearLocalSessionState();

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'status' => 'expired',
            'reason' => 'concurrent_login',
            'error' => 'Sua conta foi acessada em outro dispositivo.',
            'message' => 'Sua conta foi acessada em outro dispositivo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: index.php?page=login&concurrent=1');
    exit;
}
