<?php

try {
    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }
    if (!userHasAccess('admin')) {
        ApiResponse::forbidden('Apenas administradores podem acessar o health check.');
    }

    $health = [
        'status' => 'ok',
        'checks' => [
            'db_connection' => false,
        ]
    ];

    try {
        $db = db();
        $health['checks']['db_connection'] = true;
    } catch (Throwable $e) {
        $health['status'] = 'degraded';
    }

    ApiResponse::success($health);

} catch (Throwable $e) {
    error_log('[api_health] ' . $e->getMessage());
    ApiResponse::internalError();
}
