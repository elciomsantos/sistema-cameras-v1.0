<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    EquipamentoService::requireAccess();
    $data = EquipamentoService::parseJsonInput();

    $validator = new RequestValidator($data);
    $validator->validate([
        'id' => 'required|numeric|min:1',
        'status_id' => 'required|numeric|min:1',
        'procedimento_id' => 'required|numeric|min:1',
        'regiao_id' => 'required|numeric|min:1',
        'tipo_id' => 'required|numeric|min:1',
        'local_id' => 'required|numeric|min:1',
        'secretaria_id' => 'required|numeric|min:1',
        'marca_id' => 'required|numeric|min:1',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $svc = new EquipamentoService();
    $svc->beginTransaction();

    $fields = $svc->extractCommonData($data);
    $equipId = (int)($data['id'] ?? 0);
    $localId = (int)($data['local_id'] ?? 0);

    if ($equipId < 1 || $localId < 1) {
        ApiResponse::error('VALIDATION_ERROR', 'IDs inválidos.');
    }

    if (!verificarEscopoEquipamento($equipId)) {
        ApiResponse::forbidden('Camera nao pertence a sua secretaria.');
    }

    // Verifica duplicidade por patrimonio ou numero de serie ( entre ativos, excluindo o proprio )
    $dup = $svc->checkDuplicidade($fields['patrimonio'], $fields['numero_serie'], $equipId);
    if ($dup['duplicado']) {
        $campoLabel = $dup['campo'] === 'patrimonio' ? 'Patrimônio' : 'Número de série';
        ApiResponse::error(
            'CONFLICT',
            "Outro equipamento já usa este {$campoLabel} (ID: {$dup['equipamento_id']}). Duplicidade não permitida."
        );
    }

    $svc->checkLocationExists($localId);
    $fields['classif_endereco_id'] = $svc->resolveClassificacaoEndereco(
        $fields['classif_endereco_id'],
        $data['tipo_logradouro'] ?? null
    );

    // Preserve existing model when not submitted (e.g., user only changed status)
    if (empty($data['modelo_existente']) && empty($data['novo_modelo_nome'])) {
        $current = db()->query(
            "SELECT modelo_id FROM equipamentos WHERE id = ?",
            [$equipId]
        );
        if ($current['status'] === 'success' && !empty($current['data'])) {
            $data['modelo_existente'] = (int)$current['data'][0]->modelo_id;
        } else {
            ApiResponse::error('VALIDATION_ERROR', 'Não foi possível recuperar o modelo do equipamento.');
        }
    }
    $modeloId = $svc->resolveModelo($fields['marca_id'], $fields['tipo_id'], $data);
    $svc->validateCoordenadas($fields['coordenadas']);
    $svc->validateTipoSpecific($fields['tipo_id'], $fields['dvr_modelo'], $fields['totem_quantidade_cameras']);

    $svc->setAppUserContext($_SESSION['usuario']->id ?? null);

    try {
        $equipData = $svc->buildEquipData($fields, $localId, $modeloId);
        $svc->updateEquipamento($equipData, $equipId);

        $svc->saveTipoSpecificUpsert($equipId, $fields['tipo_id'], $data);

        db()->query(
            "UPDATE locais SET
                tem_alarme = ?,
                alarme_conta = ?,
                classificacao_endereco_id = COALESCE(?, classificacao_endereco_id),
                logradouro = COALESCE(?, logradouro), bairro = COALESCE(?, bairro),
                cidade = COALESCE(?, cidade), uf = COALESCE(?, uf),
                cep = COALESCE(?, cep), numero = COALESCE(?, numero),
                descricao_posicao = COALESCE(?, descricao_posicao) WHERE id = ?",
            [$fields['tem_alarme'], $fields['alarme_conta'], $fields['classif_endereco_id'], $fields['logradouro'], $fields['bairro'], $fields['cidade'], $fields['uf'], $fields['cep'], $fields['numero'], $fields['descricao_posicao'], $localId]
        );

        $svc->commit();
    } finally {
        $svc->resetAppUserContext();
    }

    ApiResponse::success([
        'message' => 'Equipamento atualizado com sucesso!',
        'redirect' => '../../index.php?page=controle_cameras',
    ]);

} catch (Throwable $e) {
    try {
        if (isset($svc) && db()->getConnection()->inTransaction()) {
            $svc->rollback();
        }
    } catch (Exception $rollbackEx) {
        error_log('[api_editar_camera] Rollback falhou: ' . $rollbackEx->getMessage());
    }

    $msg = $e->getMessage();
    error_log('[api_editar_camera] ' . $msg . ' | File: ' . $e->getFile() . ':' . $e->getLine());
    ApiResponse::internalError('Erro ao editar câmera: ' . $msg);
}
