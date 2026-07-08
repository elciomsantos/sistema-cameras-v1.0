<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    requireApiAuth('user', true);

    $validator = new RequestValidator($_GET);
    $validator->validate([
        'id' => 'numeric|min:0',
        'page_num' => 'numeric|min:1',
        'per_page' => 'numeric|min:1|max:100',
        'status' => 'string|max:50',
        'regiao' => 'string|max:50',
        'conta' => 'numeric|min:0',
        'nome' => 'string|max:100',
        'busca' => 'string|max:100',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $id = max(0, (int)($_GET['id'] ?? 0));
    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 1) $perPage = 20;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    $status = trim((string)($_GET['status'] ?? ''));
    $regiao = trim((string)($_GET['regiao'] ?? ''));
    $conta = trim((string)($_GET['conta'] ?? ''));
    $nome = trim((string)($_GET['nome'] ?? ''));
    $busca = trim((string)($_GET['busca'] ?? ''));

    $db = db();
    $where = [];
    $params = [];

    $usuarioSecretariaId = getUsuarioSecretariaId();
    if ($usuarioSecretariaId !== null && resolveUserAccessLevel($_SESSION['usuario'] ?? null) === 'user') {
        $where[] = 'EXISTS (SELECT 1 FROM locais l WHERE l.alarme_conta = a.conta AND l.secretaria_id = :secretaria_id)';
        $params[':secretaria_id'] = $usuarioSecretariaId;
    }

    if ($id > 0) {
        $where[] = 'a.id = :id';
        $params[':id'] = $id;
    }
    if ($status !== '') {
        $where[] = 'a.status_id = (SELECT id FROM status WHERE nome = :status LIMIT 1)';
        $params[':status'] = strtoupper($status);
    }
    if ($regiao !== '') {
        $where[] = 'a.regiao_id = (SELECT id FROM regioes WHERE nome = :regiao LIMIT 1)';
        $params[':regiao'] = strtoupper($regiao);
    }
    if ($conta !== '' && ctype_digit($conta)) {
        $where[] = 'a.conta = :conta';
        $params[':conta'] = (int)$conta;
    }
    if ($nome !== '') {
        $where[] = '(a.local LIKE :nome OR a.endereco LIKE :nome)';
        $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $nome);
        $params[':nome'] = '%' . $likeSafe . '%';
    }

    if ($busca !== '') {
        $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $busca);
        $buscaParam = '%' . $likeSafe . '%';

        $buscaParts = [];
        $isIp = filter_var($busca, FILTER_VALIDATE_IP);

        if ($isIp) {
            $ipParts = [];
            if (filter_var($busca, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipParts[] = 'INET_ATON(a.ip) = INET_ATON(:busca_ip)';
                $ipParts[] = 'INET_ATON(a.ip_dvr) = INET_ATON(:busca_ip2)';
            } else {
                $ipParts[] = 'a.ip = :busca_ip';
                $ipParts[] = 'a.ip_dvr = :busca_ip2';
            }
            $params[':busca_ip'] = $busca;
            $params[':busca_ip2'] = $busca;
            $buscaParts[] = '(' . implode(' OR ', $ipParts) . ')';
        } else {
            $buscaParts[] = '(a.ip LIKE :busca_ip_like OR a.ip_dvr LIKE :busca_ip_like2 OR a.mac LIKE :busca_mac)';
            $params[':busca_ip_like'] = $buscaParam;
            $params[':busca_ip_like2'] = $buscaParam;
            $params[':busca_mac'] = $buscaParam;

            if (ctype_digit($busca)) {
                $buscaParts[] = 'a.conta = :busca_conta';
                $params[':busca_conta'] = (int)$busca;
            }

            $buscaParts[] = 'a.numero_sei LIKE :busca_sei';
            $params[':busca_sei'] = $buscaParam;

            $buscaParts[] = 'CAST(a.id AS CHAR) LIKE :busca_id';
            $params[':busca_id'] = $buscaParam;

            if (strlen($busca) >= 3) {
                $buscaParts[] = 'MATCH(a.local, a.endereco, a.observacao) AGAINST(:busca_fulltext IN BOOLEAN MODE)';
                $params[':busca_fulltext'] = $busca . '*';
            } else {
                $buscaParts[] = '(a.local LIKE :busca_local OR a.endereco LIKE :busca_end OR a.observacao LIKE :busca_obs)';
                $params[':busca_local'] = $buscaParam;
                $params[':busca_end'] = $buscaParam;
                $params[':busca_obs'] = $buscaParam;
            }
        }

        $where[] = '(' . implode(' OR ', $buscaParts) . ')';
    }

    $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

    $countSql = "SELECT COUNT(*) AS total FROM central_alarmes a {$whereSql}";
    $countResult = $db->query($countSql, $params);

    if (!$countResult || $countResult['status'] !== 'success') {
        $msg = (string)($countResult['error'] ?? '');
        if (stripos($msg, "doesn't exist") !== false || stripos($msg, 'Base table or view not found') !== false) {
            error_log('[api_alarmes] Tabela de alarmes não encontrada: ' . $msg);
            ApiResponse::internalError('Erro ao carregar alarmes.');
        }
        ApiResponse::internalError('Erro ao carregar alarmes.');
    }

    $total = !empty($countResult['data']) ? (int)($countResult['data'][0]->total ?? 0) : 0;

    $startTime = microtime(true);

    $sql = "
        SELECT
            a.id,
            a.conta,
            COALESCE(s.nome, a.status) AS status,
            COALESCE(r.nome, a.regiao) AS regiao,
            a.local,
            a.endereco,
            a.numero,
            a.pgm1,
            a.pgm2,
            a.mac,
            a.ip,
            a.integracao,
            a.camera_gm,
            a.quant_camera_gm,
            a.ip_dvr,
            a.cameras_dvr,
            a.modelo_alarme_id,
            a.quant_repetidor,
            a.qtde_sensores,
            a.documentacao,
            a.monitorada,
            a.numero_sei,
            a.data_atualizacao,
            a.observacao,
            m.nome AS modelo_central
        FROM central_alarmes a
        LEFT JOIN status s ON a.status_id = s.id
        LEFT JOIN regioes r ON a.regiao_id = r.id
        LEFT JOIN catalogo_modelos_alarmes m ON m.id = a.modelo_alarme_id
        {$whereSql}
        ORDER BY a.data_atualizacao DESC, a.id DESC
        LIMIT :limit_val OFFSET :offset_val
    ";

    $params[':limit_val'] = $perPage;
    $params[':offset_val'] = $offset;
    $result = $db->query($sql, $params);

    $queryTime = microtime(true) - $startTime;
    if ($queryTime > 1.0) {
        error_log("Consulta lenta em api_alarmes: {$queryTime}s");
    }

    if (!$result || $result['status'] !== 'success') {
        $msg = (string)($result['error'] ?? '');
        if (stripos($msg, "doesn't exist") !== false || stripos($msg, 'Base table or view not found') !== false) {
            ApiResponse::error('NOT_FOUND', 'Tabela central_alarmes não encontrada.');
        }
        ApiResponse::internalError('Erro ao consultar alarmes.');
    }

    $rows = $result['data'] ?? [];
    ApiResponse::paginated($rows, $page, $perPage, $total);

} catch (Throwable $e) {
    error_log('[api_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao carregar alarmes.');
}
