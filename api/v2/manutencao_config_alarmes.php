<?php

/**
 * Config declarativa de manutenção de alarmes (R-001).
 * SQL e regras específicas da entidade; o fluxo vive em ManutencaoService.
 */

return [
    'table' => 'alarmes_manutencoes',
    'entity_id_param' => 'alarme_id',
    'entity_column' => 'alarme_id',
    'entity_title' => 'Alarme',
    'entity_resource' => 'alarme',
    'scope_verifier' => 'verificarEscopoAlarme',
    'scope_message' => 'Alarme não pertence à sua secretaria.',
    'mismatch_message' => 'A ordem de serviço selecionada não corresponde ao alarme informado.',
    'audit' => true,
    'validate_numero_os' => false,
    'list_payload_key' => 'alarme',
    'list_cache_key' => null,
    'list_sql' => <<<'SQL'
        SELECT a.id, a.conta, a.local, a.endereco, a.ip,
                COALESCE(s.nome, a.status) AS status, m.nome AS modelo_central
        FROM central_alarmes a
        LEFT JOIN status s ON a.status_id = s.id
        LEFT JOIN catalogo_modelos_alarmes m ON m.id = a.modelo_alarme_id
        ORDER BY a.local ASC, a.id ASC
        LIMIT 1000
        SQL,
    'pending_sql' => <<<'SQL'
        SELECT
            m.id, m.alarme_id, m.numero_os, m.problemas, m.data_hora, m.created_at,
            COALESCE(a.local, CONCAT('ALARME ', a.id)) AS alarme_nome,
            a.ip, a.conta, u.nome AS usuario_nome
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 1 {{where}}
        ORDER BY m.created_at DESC
        LIMIT 200
        SQL,
    'executing_sql' => <<<'SQL'
        SELECT
            m.id, m.alarme_id, m.procedimento_id, m.numero_os, m.problemas,
            m.data_hora, m.created_at,
            COALESCE(a.local, CONCAT('ALARME ', a.id)) AS alarme_nome,
            a.ip, a.conta, u.nome AS usuario_nome
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 2 {{where}}
        ORDER BY m.updated_at DESC, m.created_at DESC
        LIMIT 200
        SQL,
    'count_sql' => <<<'SQL'
        SELECT COUNT(*) AS total
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {{historico_where}}
        SQL,
    'history_sql' => <<<'SQL'
        SELECT
            m.id,
            m.alarme_id,
            m.numero_os,
            m.tecnico,
            m.local_servico,
            m.endereco_servico,
            m.descricao,
            m.pecas_previstas,
            m.problemas,
            m.data_hora,
            m.data_execucao,
            m.created_at,
            COALESCE(a.local, CONCAT('ALARME ', a.id)) AS alarme_nome,
            a.ip,
            a.conta,
            m2.nome AS modelo_nome,
            p.nome AS procedimento_nome,
            s.nome AS status_nome,
            u.nome AS usuario_nome
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        LEFT JOIN catalogo_modelos_alarmes m2 ON m2.id = a.modelo_alarme_id
        LEFT JOIN procedimentos p ON p.id = m.procedimento_id
        LEFT JOIN status s ON s.id = m.status_id
        LEFT JOIN usuarios u ON u.id = COALESCE(m.executado_por, m.created_by)
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {{historico_where}}
        {{order}}
        LIMIT ? OFFSET ?
        SQL,
    'sort_map' => [
        'data_hora' => 'COALESCE(m.data_execucao, m.data_hora, m.created_at)',
        'numero_os' => 'm.numero_os',
        'alarme' => 'a.local',
        'procedimento' => 'p.nome',
        'status' => 's.nome',
        'tecnico' => 'm.tecnico',
        'descricao' => 'm.descricao',
        'pecas_previstas' => 'm.pecas_previstas',
        'usuario' => 'u.nome',
    ],
    'search_builder' => function (array $whereParts, array $params, string $busca): array {
        $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $busca);
        $paramVal = '%' . $likeSafe . '%';
        if (ctype_digit($busca)) {
            $buscaParts = [
                'm.local_servico = :busca_conta',
                'm.endereco_servico = :busca_conta2',
                'a.ip = :busca_conta3',
                'm.descricao LIKE :busca1',
                'm.tecnico LIKE :busca2',
                'a.conta = :busca_conta_num',
            ];
            $params[':busca_conta'] = $busca;
            $params[':busca_conta2'] = $busca;
            $params[':busca_conta3'] = $busca;
            $params[':busca1'] = $paramVal;
            $params[':busca2'] = $paramVal;
            $params[':busca_conta_num'] = (int)$busca;
        } else {
            $buscaParts = [
                'm.local_servico LIKE :busca1',
                'm.endereco_servico LIKE :busca2',
                'a.ip LIKE :busca3',
                'm.descricao LIKE :busca4',
                'm.tecnico LIKE :busca5',
                'm.numero_os LIKE :busca6',
                'm.pecas_previstas LIKE :busca7',
                'a.local LIKE :busca8',
            ];
            $params[':busca1'] = $paramVal;
            $params[':busca2'] = $paramVal;
            $params[':busca3'] = $paramVal;
            $params[':busca4'] = $paramVal;
            $params[':busca5'] = $paramVal;
            $params[':busca6'] = $paramVal;
            $params[':busca7'] = $paramVal;
            $params[':busca8'] = $paramVal;
        }
        $whereParts[] = '(' . implode(' OR ', $buscaParts) . ')';
        return [$whereParts, $params];
    },
    'history_search_builder' => function (array $historicoParts, array $historicoParams, string $busca): array {
        $historicoParts = array_values(array_filter($historicoParts, function ($p) {
            return strpos($p, ':busca1') === false && strpos($p, ':busca2') === false &&
                   strpos($p, ':busca3') === false && strpos($p, ':busca4') === false &&
                   strpos($p, ':busca5') === false;
        }));
        $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $busca);
        $paramVal = '%' . $likeSafe . '%';
        $historicBuscaParts = [
            'm.local_servico LIKE :bh01',
            'm.endereco_servico LIKE :bh02',
            'a.ip LIKE :bh03',
            'm.descricao LIKE :bh04',
            'm.tecnico LIKE :bh05',
            'm.numero_os LIKE :bh06',
            'm.pecas_previstas LIKE :bh07',
            'a.local LIKE :bh08',
        ];
        $historicoParams[':bh01'] = $paramVal;
        $historicoParams[':bh02'] = $paramVal;
        $historicoParams[':bh03'] = $paramVal;
        $historicoParams[':bh04'] = $paramVal;
        $historicoParams[':bh05'] = $paramVal;
        $historicoParams[':bh06'] = $paramVal;
        $historicoParams[':bh07'] = $paramVal;
        $historicoParams[':bh08'] = $paramVal;

        if (ctype_digit($busca)) {
            $historicBuscaParts[] = 'a.conta = :bh09';
            $historicoParams[':bh09'] = (int)$busca;
        } else {
            $historicBuscaParts[] = 'CAST(a.conta AS CHAR) LIKE :bh09';
            $historicoParams[':bh09'] = $paramVal;
        }
        $historicoParts[] = '(' . implode(' OR ', $historicBuscaParts) . ')';
        unset(
            $historicoParams[':busca1'], $historicoParams[':busca2'], $historicoParams[':busca3'],
            $historicoParams[':busca4'], $historicoParams[':busca5'],
            $historicoParams[':busca_conta'], $historicoParams[':busca_conta2'],
            $historicoParams[':busca_conta3'], $historicoParams[':busca_conta_num']
        );
        return [$historicoParts, $historicoParams];
    },
    'create_rules' => ['alarme_id' => 'required|numeric|min:1'],
    'create_entity_lookup' => 'SELECT id FROM central_alarmes WHERE id = :id LIMIT 1',
    'create_insert' => function (array $payload, array $entityData, string $dataHora, int $usuarioId, int $entityId): array {
        return [
            "INSERT INTO alarmes_manutencoes
                (alarme_id, numero_os, problemas, data_hora, descricao, created_by, os_status_id)
             VALUES
                (:alarme_id, :numero_os, :problemas, :data_hora, :descricao, :created_by, 1)",
            [
                ':alarme_id' => $entityId,
                ':numero_os' => trim((string)($payload['numero_os'] ?? '')) ?: null,
                ':problemas' => trim((string)($payload['problemas'] ?? '')),
                ':data_hora' => $dataHora,
                ':descricao' => '',
                ':created_by' => $usuarioId,
            ],
        ];
    },
    'finalize_extra' => function (\database $db, int $entityId, int $osId, ?int $statusId, int $usuarioId, string $descricao): void {
        if ($statusId === null || $statusId <= 0) {
            return;
        }
        $updateStatus = $db->query(
            "UPDATE alarmes_manutencoes SET status_novo = (SELECT nome FROM status WHERE id = :sid)
             WHERE id = :id",
            [
                ':sid' => $statusId,
                ':id' => $osId,
            ]
        );
        if ($updateStatus['status'] !== 'success') {
            throw new RuntimeException('Falha ao atualizar status_novo.');
        }
    },
];
