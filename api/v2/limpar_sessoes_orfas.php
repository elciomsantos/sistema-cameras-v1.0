<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido.');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }
    if (!userHasAccess('admin')) {
        ApiResponse::forbidden('Apenas administradores podem limpar sessões órfãs.');
    }

    $db = db();
    $cutoff = date('Y-m-d H:i:s', time() - 86400 * 30);
    $result = $db->query(
        "DELETE FROM user_sessions WHERE active = 0 AND updated_at < ?",
        [$cutoff]
    );
    $removidas = $result['affected_rows'] ?? 0;

    ApiResponse::success(['message' => "$removidas sessões órfãs removidas."]);
} catch (Throwable $e) {
    error_log('[limpar_sessoes_orfas] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao limpar sessões órfãs.');
}
