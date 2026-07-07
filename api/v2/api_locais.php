<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    requireApiAuth('user');

    $db = db();

    $q = trim($_GET['q'] ?? '');
    $tipoLocalId = filter_input(INPUT_GET, 'tipo_local_id', FILTER_VALIDATE_INT);

    $secretariaId = getUsuarioSecretariaId();
    $nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);

    $where = ['1=1'];
    $params = [];

    if ($q !== '') {
        $where[] = 'UPPER(nome) LIKE UPPER(?)';
        $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    }

    if ($tipoLocalId) {
        $where[] = 'tipo_local_id = ?';
        $params[] = $tipoLocalId;
    }

    if ($nivel !== 'admin' && $secretariaId !== null) {
        $where[] = 'secretaria_id = ?';
        $params[] = $secretariaId;
    }

    $sql = "SELECT id, nome FROM locais WHERE " . implode(' AND ', $where) . " ORDER BY nome LIMIT 20";
    $result = $db->query($sql, $params);

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
