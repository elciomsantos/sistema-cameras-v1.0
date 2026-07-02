<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }
    if (!userHasAccess('supervisor')) {
        ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
    }

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

    $check = $db->query("SELECT id FROM equipamentos WHERE id = ?", [$id]);
    if (empty($check['data'])) {
        ApiResponse::notFound('câmera', $id);
    }

    $db->beginTransaction();

    $db->query("SET @app_user_id = ?", [$_SESSION['usuario']->id ?? null]);
    $db->query("SET @app_origem = 'api'");
    $result = $db->query("UPDATE equipamentos SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$id]);

    if ($result['status'] === 'success' && !empty($result['affected_rows']) && $result['affected_rows'] > 0) {
        $db->commit();
        ApiResponse::success([
            'message' => 'Câmera excluída com sucesso',
        ]);
        exit;
    }

    $db->rollback();
    ApiResponse::error('NOT_FOUND', 'Câmera já foi excluída ou não encontrada.');

} catch (Throwable $e) {
    if (isset($db) && $db instanceof database && $db->inTransaction()) {
        $db->rollback();
    }
    error_log('[api_excluir_camera] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao excluir câmera.');
}
