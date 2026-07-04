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
            error_log('Erro ao invalidar sessao: ' . $e->getMessage());
        }
    }
    clearLocalSessionState();
}

function verificarTimeout($isApi = false, $tempo_maximo = SESSION_TIMEOUT)
{
    if (!isset($_SESSION['usuario'])) {
        return 0;
    }

    $now = time();
    if (!isset($_SESSION['session_started_at'])) {
        invalidateAndClearSession();
        if ($isApi) {
            sendTimeoutResponse('Sessao invalida. Realize login novamente.');
        }
        header('Location: index.php?page=login&timeout=1');
        exit;
    }

    if (SESSION_ABSOLUTE_TIMEOUT > 0) {
        $elapsed = $now - (int)$_SESSION['session_started_at'];
        if ($elapsed >= SESSION_ABSOLUTE_TIMEOUT) {
            invalidateAndClearSession();
            if ($isApi) {
                sendTimeoutResponse('Sessao encerrada por tempo maximo', 'absolute_timeout');
            }
            header('Location: index.php?page=login&timeout=1');
            exit;
        }
    }

    if (!isset($_SESSION['ultimo_acesso'])) {
        $_SESSION['ultimo_acesso'] = $now;
        return $tempo_maximo;
    }

    $inatividade = $now - $_SESSION['ultimo_acesso'];
    $time_left = $tempo_maximo - $inatividade;

    if ($inatividade >= $tempo_maximo) {
        invalidateAndClearSession();
        if ($isApi) {
            sendTimeoutResponse('Sessao encerrada por inatividade');
        }
        header('Location: index.php?page=login&timeout=1');
        exit;
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
        error_log('Erro ao validar sessao unica (fallback: rejeitando sessao): ' . $e->getMessage());
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
