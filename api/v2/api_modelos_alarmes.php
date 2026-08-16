<?php

use App\Api\ApiResponse;
use App\Services\LookupService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    $lookup = new LookupService();
    $modelos = $lookup->getModelosAlarmes();

    ApiResponse::success(['modelos' => $modelos]);
} catch (Throwable $e) {
    error_log('[api_modelos_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao buscar modelos de alarmes.');
}
