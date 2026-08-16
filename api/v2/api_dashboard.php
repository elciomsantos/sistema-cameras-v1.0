<?php

use App\Api\ApiResponse;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    $currentUser = $_SESSION['usuario'];

    $secretariaId = (int)($currentUser->secretaria_id ?? 0);
    $dashboardCacheKey = "dashboard:{$secretariaId}";

    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($dashboardCacheKey);
        if (is_array($cached)) {
            ApiResponse::success($cached);
        }
    }

    $db = db();

    $usuarioSecretariaId = getUsuarioSecretariaId();
    $escoparPorSecretaria = $usuarioSecretariaId !== null && resolveUserAccessLevel($_SESSION['usuario'] ?? null) === 'user';
    $equipScope = '';
    $alarmScope = '';
    $equipParams = [];
    $alarmParams = [];
    if ($escoparPorSecretaria) {
        $equipScope = ' AND e.secretaria_id = :secretaria_id_equip';
        $equipParams[':secretaria_id_equip'] = $usuarioSecretariaId;
        $alarmScope = ' AND EXISTS (SELECT 1 FROM locais l_sec WHERE l_sec.alarme_conta = ca.conta AND l_sec.secretaria_id = :secretaria_id_alarm)';
        $alarmParams[':secretaria_id_alarm'] = $usuarioSecretariaId;
    }

    $stats = [
        'total' => 0,
        'ativas' => 0,
        'manutencao' => 0,
        'desativadas' => 0,
        'atrasada' => 0,
        'uptime' => 0,
        'alarmes_total' => 0,
        'alarmes_manutencao' => 0,
        'alarmes_atrasadas' => 0,
    ];

    $result = $db->query("
        SELECT
            COALESCE(s.nome, 'SEM STATUS') as status,
            COUNT(*) as quantidade
        FROM equipamentos e
        LEFT JOIN status s ON e.status_id = s.id
        WHERE e.deleted_at IS NULL$equipScope
        GROUP BY s.id, s.nome
        ORDER BY quantidade DESC
    ", $equipParams);
    $statusData = $result['status'] === 'success' ? $result['data'] : [];
    foreach ($statusData as $row) {
        $nome = $row->status ?? '';
        $qtd = (int)($row->quantidade ?? 0);
        $stats['total'] += $qtd;
        if ($nome === 'FUNCIONANDO') {
            $stats['ativas'] = $qtd;
        }
        if ($nome === 'MANUTENCAO') {
            $stats['manutencao'] = $qtd;
        }
        if ($nome === 'DESATIVADA') {
            $stats['desativadas'] = $qtd;
        }
    }

    $result = $db->query("
        SELECT COUNT(DISTINCT m.equipamento_id) as atrasada
        FROM equipamentos_manutencoes m
        JOIN equipamentos e ON m.equipamento_id = e.id
        WHERE e.deleted_at IS NULL$equipScope
          AND m.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND e.status_id = (SELECT id FROM status WHERE nome = 'MANUTENCAO' LIMIT 1)
    ", $equipParams);
    $stats['atrasada'] = $result['status'] === 'success' ? (int)($result['data'][0]->atrasada ?? 0) : 0;

    $stats['uptime'] = $stats['total'] > 0 ? (int)round(($stats['ativas'] / $stats['total']) * 100) : 0;

    $result = $db->query("
        SELECT
            COALESCE(s.nome, 'SEM STATUS') as status,
            COUNT(*) as quantidade
        FROM central_alarmes ca
        LEFT JOIN status s ON ca.status_id = s.id
        WHERE ca.deleted_at IS NULL$alarmScope
        GROUP BY ca.status_id, s.nome
        ORDER BY quantidade DESC
    ", $alarmParams);
    $alarmStatusData = $result['status'] === 'success' ? $result['data'] : [];
    foreach ($alarmStatusData as $row) {
        $nome = $row->status ?? '';
        $qtd = (int)($row->quantidade ?? 0);
        $stats['alarmes_total'] += $qtd;
        $resolvedNome = ($nome === 'SEM STATUS') ? 'ATIVO' : $nome;
        if ($resolvedNome === 'ATIVO') {
            $stats['alarmes_manutencao'] += $qtd;
        }
    }

    $result = $db->query("
        SELECT COUNT(DISTINCT am.alarme_id) as atrasadas
        FROM alarmes_manutencoes am
        JOIN central_alarmes ca ON am.alarme_id = ca.id
        WHERE am.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND am.os_status_id IN (1, 2)
          AND ca.deleted_at IS NULL$alarmScope
    ", $alarmParams);
    $stats['alarmes_atrasadas'] = $result['status'] === 'success' ? (int)($result['data'][0]->atrasadas ?? 0) : 0;

    $result = $db->query("
        SELECT COALESCE(te.nome, 'SEM TIPO') as tipo, COUNT(*) as quantidade
        FROM equipamentos e
        LEFT JOIN tipos_equipamento te ON e.tipo_equipamento_id = te.id
        WHERE e.deleted_at IS NULL$equipScope
        GROUP BY te.id, te.nome
        ORDER BY quantidade DESC
    ", $equipParams);
    $tipos = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT
            COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) as camera_nome,
            e.numero_serie as serie_mac,
            m.descricao,
            m.data_hora,
            sec.nome as tecnico_responsavel,
            s.nome as status
        FROM equipamentos_manutencoes m
        JOIN equipamentos e ON m.equipamento_id = e.id
        LEFT JOIN secretarias sec ON m.secretaria_id = sec.id
        LEFT JOIN status s ON e.status_id = s.id
        LEFT JOIN locais l ON e.local_id = l.id
        WHERE e.deleted_at IS NULL$equipScope
        ORDER BY m.data_hora DESC
        LIMIT 5
    ", $equipParams);
    $manutencoes = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT
            e.id,
            COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) as nome,
            e.numero_serie as numero_serie,
            l.nome as localizacao,
            s.nome as status,
            DATEDIFF(NOW(), m.data_hora) as dias_atraso,
            m.data_hora as ultima_manutencao
        FROM equipamentos e
        JOIN status s ON e.status_id = s.id
        LEFT JOIN locais l ON e.local_id = l.id
        LEFT JOIN (
            SELECT equipamento_id, MAX(data_hora) as data_hora
            FROM equipamentos_manutencoes
            WHERE os_status_id IN (1, 2)
            GROUP BY equipamento_id
        ) m ON m.equipamento_id = e.id
        WHERE e.deleted_at IS NULL$equipScope
          AND s.nome = 'MANUTENCAO'
          AND (m.data_hora IS NULL OR m.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY))
        ORDER BY m.data_hora ASC
        LIMIT 5
    ", $equipParams);
    $problemas = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT COALESCE(tc.nome, 'SEM TIPO') as tipo, COUNT(*) as quantidade
        FROM equipamentos e
        LEFT JOIN tipo_cameras tc ON e.tipo_camera_id = tc.id
        WHERE e.deleted_at IS NULL$equipScope
        GROUP BY tc.nome
        ORDER BY quantidade DESC
    ", $equipParams);
    $cameraTipoData = $result['status'] === 'success' ? $result['data'] : [];

    $responsePayload = [
        'stats' => $stats,
        'tipos' => $tipos,
        'manutencoes' => $manutencoes,
        'problemas' => $problemas,
        'status_data' => $statusData,
        'alarm_status_data' => $alarmStatusData,
        'camera_tipo_data' => $cameraTipoData,
        'user_info' => [
            'nome' => $currentUser->nome ?? $currentUser->usuario ?? 'Usuário',
            'nivel_acesso' => $currentUser->nivel_acesso ?? 'user'
        ],
    ];

    if (function_exists('apcu_store')) {
        apcu_store($dashboardCacheKey, $responsePayload, 60);
    }

    ApiResponse::success($responsePayload);
} catch (Throwable $e) {
    error_log('[api_dashboard] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao carregar dashboard.');
}
