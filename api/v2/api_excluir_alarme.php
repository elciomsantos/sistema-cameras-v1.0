<?php

use App\Exception\ServiceException;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST e permitido');
    }

    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody, true);
    if (!is_array($body)) {
        ApiResponse::error('BAD_REQUEST', 'Corpo da requisicao deve ser JSON.');
    }
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        ApiResponse::error('VALIDATION_ERROR', 'ID nao informado.');
    }

    $db = db();

    $check = $db->query(
        "SELECT id FROM central_alarmes WHERE id = ? AND deleted_at IS NULL",
        [$id]
    );
    if (empty($check['data'])) {
        ApiResponse::notFound('alarme', $id);
    }

    if (!verificarEscopoAlarme($id)) {
        ApiResponse::forbidden('Alarme nao pertence a sua secretaria.');
    }

    $svc = new AlarmeService($db);
    $svc->excluir($id);

    invalidateListCaches();

    ApiResponse::success([
        'message' => 'Alarme excluido com sucesso',
    ]);

} catch (ServiceException $e) {
    ApiResponse::error($e->getErrorCode(), $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_excluir_alarme] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao excluir alarme.');
}
