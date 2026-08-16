<?php

use App\Exception\ServiceException;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST e permitido');
    }

    $svc = new AlarmeService();
    $svc->requireAccess();

    $payload = $svc->parseJsonInput();
    $params = $svc->validateInput($payload, false);

    $id = $svc->cadastrar($params);

    invalidateListCaches();

    ApiResponse::created([
        'alarme_id' => $id,
        'redirect' => '../../index.php?page=editar_alarmes&id=' . $id,
    ], $id);
} catch (ServiceException $e) {
    ApiResponse::error($e->getErrorCode(), $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_cadastrar_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao cadastrar alarme.');
}
