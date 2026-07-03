<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    requireApiAuth('user');

    $db = db();

    $secretariaId = getUsuarioSecretariaId();
    $nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);

    if ($nivel === 'admin' || $secretariaId === null) {
        $result = $db->query("SELECT id, nome FROM locais ORDER BY nome");
    } else {
        $result = $db->query(
            "SELECT id, nome FROM locais WHERE secretaria_id = ? ORDER BY nome",
            [$secretariaId]
        );
    }

    if ($result['status'] === 'success') {
        ApiResponse::success([
            'data' => $result['data'] ?? [],
            'count' => count($result['data'] ?? []),
        ]);
    }

    ApiResponse::internalError('Erro ao buscar locais.');

} catch (Throwable $e) {
    error_log('[api_locais] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao buscar locais.');
}
