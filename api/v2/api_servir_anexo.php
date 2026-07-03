<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET e permitido', [], 405);
    }

    requireApiAuth('user');

    $id = isset($_GET['id']) ? max(1, (int)$_GET['id']) : 0;
    if (!$id) {
        ApiResponse::error('VALIDATION_ERROR', 'ID do anexo nao informado.');
    }

    $db = db();

    $result = $db->query(
        "SELECT id, caminho, mime_type, nome_original FROM equipamentos_anexos WHERE id = :id",
        [':id' => $id]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        ApiResponse::notFound('anexo', $id);
    }

    $anexo = $result['data'][0];
    $anexoId = (int)$anexo->id;

    if (!verificarEscopoAnexo($anexoId)) {
        ApiResponse::forbidden('Anexo nao pertence a sua secretaria.');
    }

    $caminhoRelativo = $anexo->caminho ?? '';

    $uploadsBase = realpath(APP_ROOT . '/storage/uploads');
    $relativePath = str_replace('\\', '/', ltrim($caminhoRelativo, '/'));

    if ($uploadsBase === false || !str_starts_with($relativePath, 'uploads/')) {
        ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
    }

    $caminhoAbsoluto = realpath($uploadsBase . '/' . substr($relativePath, 8));
    if ($caminhoAbsoluto === false) {
        // Fallback: arquivo legado em public/uploads/
        $caminhoAbsoluto = realpath(__DIR__ . '/../../public/' . $relativePath);
        if ($caminhoAbsoluto !== false) {
            $uploadsBase = realpath(__DIR__ . '/../../public/uploads');
        }
    }
    if ($caminhoAbsoluto === false) {
        ApiResponse::notFound('arquivo');
    }

    $uploadsBasePrefix = rtrim(str_replace('\\', '/', $uploadsBase), '/') . '/';
    $resolvedPath = str_replace('\\', '/', $caminhoAbsoluto);

    if (!str_starts_with($resolvedPath, $uploadsBasePrefix)) {
        ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
    }

    if (!is_file($caminhoAbsoluto) || !is_readable($caminhoAbsoluto)) {
        ApiResponse::notFound('arquivo');
    }

    $mimeType = $anexo->mime_type ?? mime_content_type($caminhoAbsoluto) ?: 'application/octet-stream';
    $allowedServeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'application/pdf'];
    if (!in_array($mimeType, $allowedServeMimes, true)) {
        $mimeType = 'application/octet-stream';
    }
    $nomeOriginal = $anexo->nome_original ?? basename($caminhoAbsoluto);
    $tamanho = filesize($caminhoAbsoluto);

    header_remove('Content-Type');
    header('Content-Type: ' . $mimeType);
    $filenameSanitized = preg_replace('/[\x00-\x1F\x7F<>"\/\\\\|:?*]/', '', $nomeOriginal);
    header('Content-Disposition: attachment; filename="' . $filenameSanitized . '"');
    header('Content-Length: ' . $tamanho);
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    readfile($caminhoAbsoluto);
    exit;

} catch (Throwable $e) {
    error_log('[api_servir_anexo] ' . $e->getMessage());
    ApiResponse::internalError();
}
