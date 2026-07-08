<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    requireApiAuth('supervisor');

    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody, true);
    if (!is_array($body)) {
        ApiResponse::error('BAD_REQUEST', 'Corpo da requisição deve ser JSON.');
    }
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        ApiResponse::error('VALIDATION_ERROR', 'ID não informado.');
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

    ApiResponse::success([
        'message' => 'Alarme excluído com sucesso',
    ]);

} catch (Throwable $e) {
    error_log('[api_excluir_alarme] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao excluir alarme.');
}
