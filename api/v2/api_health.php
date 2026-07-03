<?php

try {
    requireApiAuth('admin');

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
