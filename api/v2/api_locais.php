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
        $where[] = 'UPPER(l.nome) LIKE UPPER(?)';
        $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    }

    if ($tipoLocalId) {
        $where[] = 'l.tipo_local_id = ?';
        $params[] = $tipoLocalId;
    }

    if ($nivel !== 'admin' && $secretariaId !== null) {
        $where[] = 'l.secretaria_id = ?';
        $params[] = $secretariaId;
    }

    $sql = "SELECT l.id, l.nome, l.logradouro, l.bairro, l.cidade, l.uf, l.cep, l.numero,
                   l.descricao_posicao, l.regiao_id, l.secretaria_id, l.latitude, l.longitude,
                   l.tipo_local_id, ce.nome AS tipo_logradouro_nome
            FROM locais l
            LEFT JOIN classificacao_enderecos ce ON ce.id = l.classificacao_endereco_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.nome LIMIT 20";
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
