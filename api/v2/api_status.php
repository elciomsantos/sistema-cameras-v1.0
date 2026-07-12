<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    $lookup = new LookupService();
    $status = $lookup->getStatusList();

    ApiResponse::success([
        'data' => $status,
        'count' => count($status),
    ]);

} catch (Throwable $e) {
    error_log('[api_status] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao buscar status.');
}
