<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET e permitido', [], 405);
    }

    $id = isset($_GET['id']) ? max(1, (int)$_GET['id']) : 0;
    if (!$id) {
        ApiResponse::error('VALIDATION_ERROR', 'ID do anexo nao informado.');
    }

    $size = isset($_GET['size']) ? strtolower(trim($_GET['size'])) : null;
    $allowedSizes = ['small', 'medium'];
    if ($size !== null && !in_array($size, $allowedSizes, true)) {
        ApiResponse::error('VALIDATION_ERROR', 'Size invalido. Use: small, medium.');
    }

    $db = db();

    $result = $db->query(
        "SELECT id, caminho, mime_type, nome_original, conteudo, conteudo_comprimido, tamanho, thumbnails FROM equipamentos_anexos WHERE id = :id",
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

    // Prioridade 1: conteudo armazenado diretamente no BD
    if ($anexo->conteudo !== null) {
        $conteudo = $anexo->conteudo;
        // Se o driver PDO retornar resource, converter para string
        if (is_resource($conteudo)) {
            $conteudo = stream_get_contents($conteudo);
        }
        if ($conteudo === false || $conteudo === null || $conteudo === '') {
            ApiResponse::notFound('arquivo');
        }
        // Descomprimir se necessario
        if ((int)($anexo->conteudo_comprimido ?? 0) === 1 && function_exists('gzinflate')) {
            $inflated = @gzinflate($conteudo);
            if ($inflated !== false) {
                $conteudo = $inflated;
            }
        }
        $mimeType = $anexo->mime_type ?? 'application/octet-stream';
        $allowedServeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'application/pdf'];
        if (!in_array($mimeType, $allowedServeMimes, true)) {
            $mimeType = 'application/octet-stream';
        }
        $nomeOriginal = $anexo->nome_original ?? 'anexo';
        // tamanho armazenado = bytes do arquivo ORIGINAL (nao do comprimido)
        $tamanho = $anexo->tamanho ?? strlen($conteudo);

        $etag = sprintf('"%x-%x"', $anexoId, (int)$tamanho);
        $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($inm !== '' && $inm === $etag) {
            http_response_code(304);
            header('ETag: ' . $etag);
            header('Cache-Control: private, max-age=3600');
            exit;
        }

        header_remove('Content-Type');
        header('Content-Type: ' . $mimeType);
        $filenameSanitized = preg_replace('/[\x00-\x1F\x7F<>"\/\\\\|:?*]/', '', $nomeOriginal);
        header('Content-Disposition: attachment; filename="' . $filenameSanitized . '"');
        header('Content-Length: ' . $tamanho);
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        header('ETag: ' . $etag);

        echo $conteudo;
        exit;
    }

    // Prioridade 2: arquivo em disco (storage/uploads/ ou legacy public/uploads/)
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

    // Servir thumbnail se size foi solicitado
    if ($size !== null && is_file($caminhoAbsoluto)) {
        $thumbSuffix = $size === 'small' ? '_sm' : '_md';
        $thumbPathInfo = pathinfo($caminhoAbsoluto);
        $thumbPath = $thumbPathInfo['dirname'] . '/' . $thumbPathInfo['filename'] . $thumbSuffix . '.' . ($thumbPathInfo['extension'] ?? 'jpg');
        if (is_file($thumbPath) && is_readable($thumbPath)) {
            $caminhoAbsoluto = $thumbPath;
        }
    }

    $mimeType = $anexo->mime_type ?? mime_content_type($caminhoAbsoluto) ?: 'application/octet-stream';
    $allowedServeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'application/pdf'];
    if (!in_array($mimeType, $allowedServeMimes, true)) {
        $mimeType = 'application/octet-stream';
    }
    $nomeOriginal = $anexo->nome_original ?? basename($caminhoAbsoluto);
    $tamanho = filesize($caminhoAbsoluto);
    $mtime = filemtime($caminhoAbsoluto) ?: 0;

    $etag = sprintf('"%x-%x-%x"', $anexoId, (int)$tamanho, (int)$mtime);
    $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if ($inm !== '' && $inm === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=3600');
        exit;
    }

    header_remove('Content-Type');
    header('Content-Type: ' . $mimeType);
    $filenameSanitized = preg_replace('/[\x00-\x1F\x7F<>"\/\\\\|:?*]/', '', $nomeOriginal);
    header('Content-Disposition: attachment; filename="' . $filenameSanitized . '"');
    header('Content-Length: ' . $tamanho);
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header('ETag: ' . $etag);

    readfile($caminhoAbsoluto);
    exit;

} catch (Throwable $e) {
    error_log('[api_servir_anexo] ' . $e->getMessage());
    ApiResponse::internalError();
}
