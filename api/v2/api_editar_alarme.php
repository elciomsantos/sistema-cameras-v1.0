<?php

use App\Exception\ServiceException;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST e permitido');
    }

    $svc = new AlarmeService();
    $svc->requireAccess();

    $payload = $svc->parseJsonInput();
    $params = $svc->validateInput($payload, true);

    $id = $params['id'];
    unset($params['id']);

    if (!verificarEscopoAlarme($id)) {
        ApiResponse::forbidden('Alarme nao pertence a sua secretaria.');
    }

    $svc->atualizar($id, $params);

    invalidateListCaches();

    ApiResponse::success([
        'message' => 'Alarme atualizado com sucesso.',
        'redirect' => '../../index.php?page=controle&modulo=alarmes',
    ]);

} catch (ServiceException $e) {
    ApiResponse::error($e->getErrorCode(), $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_editar_alarme] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao atualizar alarme.');
}
