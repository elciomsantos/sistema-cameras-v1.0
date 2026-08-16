<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    $validator = new RequestValidator($_GET);
    $validator->validate([
        'marca_id' => 'required|numeric|min:1',
        'tipo_id' => 'numeric|min:1',
        'tipo_equipamento_id' => 'numeric|min:1',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $marcaId = (int)($_GET['marca_id'] ?? 0);
    $tipoId = (int)($_GET['tipo_id'] ?? $_GET['tipo_equipamento_id'] ?? 0);

    $lookup = new LookupService();
    $modelos = $lookup->getModelosCameras($marcaId, $tipoId);

    ApiResponse::success(['modelos' => $modelos]);
} catch (Throwable $e) {
    error_log('[api_modelos_cameras] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao buscar modelos.');
}
