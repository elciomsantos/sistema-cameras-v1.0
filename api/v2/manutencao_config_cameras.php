<?php

/**
 * Config declarativa de manutenção de câmeras (R-001).
 * SQL e regras específicas da entidade; o fluxo vive em ManutencaoService.
 */

return [
    'table' => 'equipamentos_manutencoes',
    'entity_id_param' => 'equipamento_id',
    'entity_column' => 'equipamento_id',
    'entity_title' => 'Câmera',
    'entity_resource' => 'câmera',
    'scope_verifier' => 'verificarEscopoEquipamento',
    'scope_message' => 'Câmera não pertence à sua secretaria.',
    'mismatch_message' => 'A ordem de serviço selecionada não corresponde à câmera informada.',
    'audit' => false,
    'validate_numero_os' => true,
    'list_payload_key' => 'cameras',
    'list_cache_key' => 'manut_cameras_list',
    'list_sql' => <<<'SQL'
        SELECT
            e.id,
            COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS descricao,
            l.nome AS local_nome,
            l.logradouro AS local_logradouro,
            l.numero AS local_numero,
            l.bairro AS local_bairro,
            l.cidade AS local_cidade,
            l.uf AS local_uf,
            l.cep AS local_cep,
            ce.descricao AS tipo_logradouro,
            IF(em_manut.cnt > 0, 1, 0) AS em_manutencao,
            e.ip,
            e.numero_serie,
            cm.nome AS modelo_nome,
            te.nome AS tipo_equipamento_nome,
            s.nome AS status_nome
        FROM equipamentos e
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN classificacao_enderecos ce ON ce.id = l.classificacao_endereco_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN status s ON s.id = e.status_id
        LEFT JOIN (
            SELECT equipamento_id, COUNT(*) AS cnt
            FROM equipamentos_manutencoes
            WHERE os_status_id IN (1, 2)
            GROUP BY equipamento_id
        ) em_manut ON em_manut.equipamento_id = e.id
        WHERE e.deleted_at IS NULL
        ORDER BY descricao ASC, e.id ASC
        SQL,
    'pending_sql' => <<<'SQL'
        SELECT
            m.id,
            m.equipamento_id,
            m.procedimento_id,
            m.numero_os,
            m.local_servico,
            m.endereco_servico,
            m.problemas,
            m.data_hora,
            m.created_at,
            COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS camera_nome,
            e.ip,
            e.numero_serie,
            cm.nome AS modelo_nome,
            te.nome AS tipo_equipamento_nome,
            u.nome AS usuario_nome
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 1 AND e.deleted_at IS NULL {{where}}
        ORDER BY m.created_at DESC
        LIMIT 200
        SQL,
    'executing_sql' => <<<'SQL'
        SELECT
            m.id,
            m.equipamento_id,
            m.procedimento_id,
            m.numero_os,
            m.local_servico,
            m.endereco_servico,
            m.problemas,
            m.data_hora,
            m.created_at,
            COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS camera_nome,
            e.ip,
            e.numero_serie,
            cm.nome AS modelo_nome,
            te.nome AS tipo_equipamento_nome,
            u.nome AS usuario_nome
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 2 AND e.deleted_at IS NULL {{where}}
        ORDER BY m.updated_at DESC, m.created_at DESC
        LIMIT 200
        SQL,
    'count_sql' => <<<'SQL'
        SELECT COUNT(*) AS total
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN status s ON s.id = m.status_id
        LEFT JOIN procedimentos p ON p.id = m.procedimento_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) AND e.deleted_at IS NULL {{historico_where}}
        SQL,
    'history_sql' => <<<'SQL'
        SELECT
            m.id,
            m.equipamento_id,
            m.numero_os,
            m.tecnico,
            m.local_servico,
            m.endereco_servico,
            m.descricao,
            m.pecas_previstas,
            m.data_hora,
            m.data_execucao,
            m.created_at,
            COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS camera_nome,
            e.ip,
            e.numero_serie,
            cm.nome AS modelo_nome,
            te.nome AS tipo_equipamento_nome,
            s.nome AS status_nome,
            p.nome AS procedimento_nome,
            u.nome AS usuario_nome
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN status s ON s.id = m.status_id
        LEFT JOIN procedimentos p ON p.id = m.procedimento_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) AND e.deleted_at IS NULL {{historico_where}}
        {{order}}
        LIMIT ? OFFSET ?
        SQL,
    'sort_map' => [
        'data_hora' => 'COALESCE(m.data_execucao, m.data_hora, m.created_at)',
        'numero_os' => 'm.numero_os',
        'camera' => "COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id))",
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
        $whereParts[] = '(m.local_servico LIKE :bc1 OR m.endereco_servico LIKE :bc2 OR e.ip LIKE :bc3)';
        $params[':bc1'] = $paramVal;
        $params[':bc2'] = $paramVal;
        $params[':bc3'] = $paramVal;
        return [$whereParts, $params];
    },
    'history_search_builder' => function (array $historicoParts, array $historicoParams, string $busca): array {
        $historicoParts = array_values(array_filter($historicoParts, function ($p) {
            return strpos($p, ':bc1') === false && strpos($p, ':bc2') === false && strpos($p, ':bc3') === false;
        }));
        $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $busca);
        $paramVal = '%' . $likeSafe . '%';
        $historicoParts[] = "(m.local_servico LIKE :bh01 OR m.endereco_servico LIKE :bh02 OR e.ip LIKE :bh03 OR m.descricao LIKE :bh04 OR m.tecnico LIKE :bh05 OR m.numero_os LIKE :bh06 OR m.pecas_previstas LIKE :bh07 OR COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) LIKE :bh08 OR cm.nome LIKE :bh09 OR p.nome LIKE :bh10 OR u.nome LIKE :bh11)";
        $historicoParams[':bh01'] = $paramVal;
        $historicoParams[':bh02'] = $paramVal;
        $historicoParams[':bh03'] = $paramVal;
        $historicoParams[':bh04'] = $paramVal;
        $historicoParams[':bh05'] = $paramVal;
        $historicoParams[':bh06'] = $paramVal;
        $historicoParams[':bh07'] = $paramVal;
        $historicoParams[':bh08'] = $paramVal;
        $historicoParams[':bh09'] = $paramVal;
        $historicoParams[':bh10'] = $paramVal;
        $historicoParams[':bh11'] = $paramVal;
        unset($historicoParams[':bc1'], $historicoParams[':bc2'], $historicoParams[':bc3']);
        return [$historicoParts, $historicoParams];
    },
    'create_rules' => ['equipamento_id' => 'required|numeric|min:1'],
    'create_entity_lookup' => 'SELECT id, secretaria_id FROM equipamentos WHERE id = :id LIMIT 1',
    'create_insert' => function (array $payload, array $entityData, string $dataHora, int $usuarioId, int $entityId): array {
        $secretariaId = (int)($entityData['secretaria_id'] ?? 0);
        if ($secretariaId <= 0) {
            $secretariaId = null;
        }
        return [
            "INSERT INTO equipamentos_manutencoes
                (equipamento_id, secretaria_id, numero_os, problemas, local_servico, endereco_servico, data_hora, descricao, created_by, os_status_id)
             VALUES
                (:equipamento_id, :secretaria_id, :numero_os, :problemas, :local_servico, :endereco_servico, :data_hora, :descricao, :created_by, 1)",
            [
                ':equipamento_id' => $entityId,
                ':secretaria_id' => $secretariaId,
                ':numero_os' => trim((string)($payload['numero_os'] ?? '')) ?: null,
                ':problemas' => trim((string)($payload['problemas'] ?? '')),
                ':local_servico' => trim((string)($payload['local_servico'] ?? '')) ?: null,
                ':endereco_servico' => trim((string)($payload['endereco_servico'] ?? '')) ?: null,
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
            'UPDATE equipamentos SET status_id = :status_id WHERE id = :equipamento_id',
            [':status_id' => $statusId, ':equipamento_id' => $entityId]
        );
        if ($updateStatus['status'] !== 'success') {
            throw new RuntimeException('Falha ao atualizar status do equipamento.');
        }

        $statusHistorico = $db->query(
            'INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
             VALUES (:equipamento_id, :status_id, :observacao, :changed_by)',
            [
                ':equipamento_id' => $entityId,
                ':status_id' => $statusId,
                ':observacao' => 'Execução de ordem de serviço: ' . substr($descricao, 0, 200),
                ':changed_by' => $usuarioId,
            ]
        );
        if ($statusHistorico['status'] !== 'success') {
            throw new RuntimeException('Falha ao registrar histórico de status.');
        }
    },
];
