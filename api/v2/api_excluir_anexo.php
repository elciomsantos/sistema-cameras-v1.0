<?php

use App\Api\ApiResponse;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    $jsonInput = json_decode(file_get_contents('php://input'), true);
    $input = is_array($jsonInput) ? $jsonInput : [];
    $anexoId = max(1, (int)($input['id'] ?? 0));

    if (!$anexoId) {
        ApiResponse::error('VALIDATION_ERROR', 'ID do anexo não informado.');
    }

    $db = db();

    $result = $db->query(
        "SELECT id, caminho, equipamento_id, alarme_id, manutencao_camera_id, manutencao_alarme_id FROM equipamentos_anexos WHERE id = :id",
        [':id' => $anexoId]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        ApiResponse::notFound('anexo', $anexoId);
    }

    $anexo = $result['data'][0];

    if (!verificarEscopoAnexo($anexoId)) {
        ApiResponse::forbidden('Anexo nao pertence a sua secretaria.');
    }

    $before = (array)$anexo;

    $relativePath = str_replace('\\', '/', ltrim((string)$anexo->caminho, '/'));

    if (!str_starts_with($relativePath, 'uploads/')) {
        error_log('[api_excluir_anexo] Caminho de anexo fora de uploads: ' . $relativePath);
        ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
    }

    $uploadsBase = realpath(APP_ROOT . '/storage/uploads');
    $caminhoAbsoluto = realpath($uploadsBase . '/' . substr($relativePath, 8));
    if ($caminhoAbsoluto === false) {
        $caminhoAbsoluto = realpath(__DIR__ . '/../../public/' . $relativePath);
        if ($caminhoAbsoluto !== false) {
            $uploadsBase = realpath(__DIR__ . '/../../public/uploads');
        }
    }
    if ($caminhoAbsoluto !== false) {
        $uploadsBasePrefix = rtrim(str_replace('\\', '/', $uploadsBase), '/') . '/';
        $resolvedPath = str_replace('\\', '/', $caminhoAbsoluto);

        if (!str_starts_with($resolvedPath, $uploadsBasePrefix)) {
            error_log('[api_excluir_anexo] Tentativa de exclusao fora de uploads: ' . $resolvedPath);
            ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
        }
    }

    $db->beginTransaction();

    $deleteResult = $db->query(
        "DELETE FROM equipamentos_anexos WHERE id = :id",
        [':id' => $anexoId]
    );

    if ($deleteResult['status'] !== 'success' || ($deleteResult['affected_rows'] ?? 0) === 0) {
        error_log('[api_excluir_anexo] DELETE falhou ou nenhuma linha afetada para anexo_id=' . $anexoId);
        ApiResponse::error('OPERATION_FAILED', 'Erro ao excluir registro do anexo.');
    }

    if ($anexo->manutencao_camera_id) {
        $entityType = 'manutencao_camera';
        $entityId = $anexo->manutencao_camera_id;
    } elseif ($anexo->manutencao_alarme_id) {
        $entityType = 'manutencao_alarme';
        $entityId = $anexo->manutencao_alarme_id;
    } else {
        $entityType = $anexo->equipamento_id ? 'equipamento' : 'alarme';
        $entityId = $anexo->equipamento_id ?? $anexo->alarme_id;
    }

    try {
        if (function_exists('auditEvent')) {
            $before['entidade_relacionada'] = $entityType;
            $before['entidade_relacionada_id'] = $entityId ? (int)$entityId : null;
            auditEvent($db, 'equipamentos_anexos', $anexoId, 'DELETE', $before, null, 'api');
        }
    } catch (Throwable $e) {
        error_log('Erro ao registrar auditoria: ' . $e->getMessage());
    }

    $db->commit();

    // Excluir thumbnails e arquivo fisico apos commit do DB
    if ($caminhoAbsoluto !== false) {
        $pathInfo = pathinfo($caminhoAbsoluto);
        if (isset($pathInfo['dirname'], $pathInfo['filename'], $pathInfo['extension'])) {
            foreach (['_sm', '_md'] as $suffix) {
                $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . $suffix . '.' . $pathInfo['extension'];
                if (is_file($thumbPath)) {
                    @unlink($thumbPath);
                }
            }
        }
        if (is_file($caminhoAbsoluto) && !@unlink($caminhoAbsoluto)) {
            error_log('[api_excluir_anexo] Nao foi possivel excluir o arquivo: ' . $caminhoAbsoluto);
        }
    }

    ApiResponse::success(['message' => 'Anexo excluido com sucesso.']);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof database && $db->inTransaction()) {
        $db->rollback();
    }
    error_log('[api_excluir_anexo] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao excluir anexo.');
}
