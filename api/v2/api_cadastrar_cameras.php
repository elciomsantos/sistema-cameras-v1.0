<?php

use App\Exception\ServiceException;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST e permitido');
    }

    EquipamentoService::requireAccess();
    $data = EquipamentoService::parseJsonInput();

    $svc = new EquipamentoService();
    $svc->validateRequiredIds($data);

    $nomeLocal = trim((string)($data['nome_local'] ?? ''));
    if ($nomeLocal === '') {
        ApiResponse::error('VALIDATION_ERROR', 'Informe o nome/local da camera.');
    }
    if (strlen($nomeLocal) > 255) {
        ApiResponse::error('VALIDATION_ERROR', 'Nome do local muito longo (max. 255 caracteres).');
    }

    $tipoLocalId = max(1, (int)($data['tipo_local_id'] ?? 0));
    $checkTipoLocal = db()->query("SELECT id FROM tipos_locais WHERE id = ? LIMIT 1", [$tipoLocalId]);
    if ($checkTipoLocal['status'] !== 'success' || empty($checkTipoLocal['data'])) {
        ApiResponse::error('VALIDATION_ERROR', 'Tipo de local selecionado nao encontrado.');
    }

    $svc->beginTransaction();

    $fields = $svc->extractCommonData($data);
    $fields['classif_endereco_id'] = $svc->resolveClassificacaoEndereco(
        $fields['classif_endereco_id'],
        $data['tipo_logradouro'] ?? null
    );

    $dup = $svc->checkDuplicidade($fields['patrimonio'], $fields['numero_serie']);
    if ($dup['duplicado']) {
        $campoLabel = $dup['campo'] === 'patrimonio' ? 'Patrimonio' : 'Numero de serie';
        ApiResponse::error(
            'CONFLICT',
            "Ja existe um equipamento cadastrado com este {$campoLabel} (ID: {$dup['equipamento_id']}). Duplicidade nao permitida."
        );
    }

    $localId = $svc->resolveLocation($data, $fields['secretaria_id'], $nomeLocal);
    $svc->updateLocationFields($localId, $data);

    $modeloId = $svc->resolveModelo($fields['marca_id'], $fields['tipo_id'], $data);
    $svc->validateCoordenadas($fields['coordenadas']);
    $svc->validateTipoSpecific($fields['tipo_id'], $fields['dvr_modelo'], $fields['totem_quantidade_cameras']);

    $svc->setAppUserContext($_SESSION['usuario']->id ?? null);

    try {
        $equipData = $svc->buildEquipData($fields, $localId, $modeloId);
        $equipId = $svc->insertEquipamento($equipData);

        $svc->saveTipoSpecificInsert($equipId, $fields['tipo_id'], $data);

        $svc->commit();
    } finally {
        $svc->resetAppUserContext();
    }

    ApiResponse::created([
        'message' => "Equipamento cadastrado com sucesso! ID: $equipId",
        'camera_id' => $equipId,
        'redirect' => 'index.php?page=controle&modulo=cameras',
    ], $equipId);

} catch (ServiceException $e) {
    ApiResponse::error($e->getErrorCode(), $e->getMessage());
} catch (Throwable $e) {
    $rolledBack = false;

    try {
        if (isset($svc) && db()->getConnection()->inTransaction()) {
            $svc->rollback();
            $rolledBack = true;
        }
    } catch (Exception $rollbackEx) {
        error_log('[api_cadastrar_cameras] CRITICAL: Rollback falhou: ' . $rollbackEx->getMessage());
    }

    if (isset($equipId) && !$rolledBack) {
        error_log('[api_cadastrar_cameras] CRITICAL: Estado inconsistente - equipId=' . ($equipId ?? 'null') . ' - rollback falhou');
    }

    error_log('[api_cadastrar_cameras] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao cadastrar equipamento.');
}
