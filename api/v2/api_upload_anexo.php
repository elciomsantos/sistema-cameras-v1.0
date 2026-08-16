<?php

use App\Api\ApiResponse;

require_once __DIR__ . '/helpers/thumbnails.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    $db = db();

    $equipamentoId = isset($_POST['equipamento_id']) ? (int)$_POST['equipamento_id'] : null;
    $alarmeId = isset($_POST['alarme_id']) ? (int)$_POST['alarme_id'] : null;
    $manutencaoCameraId = isset($_POST['manutencao_camera_id']) ? (int)$_POST['manutencao_camera_id'] : null;
    $manutencaoAlarmeId = isset($_POST['manutencao_alarme_id']) ? (int)$_POST['manutencao_alarme_id'] : null;
    $tipo = trim($_POST['tipo'] ?? 'foto');
    $descricao = trim($_POST['descricao'] ?? '');

    $fkCount = (int)(bool)$equipamentoId + (int)(bool)$alarmeId + (int)(bool)$manutencaoCameraId + (int)(bool)$manutencaoAlarmeId;
    if ($fkCount === 0) {
        ApiResponse::error('VALIDATION_ERROR', 'Informe equipamento_id, alarme_id, manutencao_camera_id ou manutencao_alarme_id.');
    }
    if ($fkCount > 1) {
        ApiResponse::error('VALIDATION_ERROR', 'Informe apenas um ID por vez (equipamento, alarme, manutenção câmera ou manutenção alarme).');
    }

    $usuarioNivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
    $usuarioSecretariaId = getUsuarioSecretariaId();

    $targetSecretariaId = null;

    if ($equipamentoId) {
        $check = $db->query("SELECT id, secretaria_id FROM equipamentos WHERE id = ? AND deleted_at IS NULL LIMIT 1", [$equipamentoId]);
        if ($check['status'] !== 'success' || empty($check['data'])) {
            ApiResponse::error('VALIDATION_ERROR', 'Equipamento não encontrado.');
        }
        $targetSecretariaId = (int)($check['data'][0]->secretaria_id ?? 0);
    } elseif ($alarmeId) {
        $check = $db->query("SELECT ca.id, l.secretaria_id FROM central_alarmes ca LEFT JOIN locais l ON l.alarme_conta = ca.conta WHERE ca.id = ? LIMIT 1", [$alarmeId]);
        if ($check['status'] !== 'success' || empty($check['data'])) {
            ApiResponse::error('VALIDATION_ERROR', 'Alarme não encontrado.');
        }
        $targetSecretariaId = (int)($check['data'][0]->secretaria_id ?? 0);
    } elseif ($manutencaoCameraId) {
        $check = $db->query("SELECT em.id, e.secretaria_id FROM equipamentos_manutencoes em JOIN equipamentos e ON e.id = em.equipamento_id WHERE em.id = ? LIMIT 1", [$manutencaoCameraId]);
        if ($check['status'] !== 'success' || empty($check['data'])) {
            ApiResponse::error('VALIDATION_ERROR', 'Manutenção de câmera não encontrada.');
        }
        $targetSecretariaId = (int)($check['data'][0]->secretaria_id ?? 0);
    } elseif ($manutencaoAlarmeId) {
        $check = $db->query("SELECT am.id, l.secretaria_id FROM alarmes_manutencoes am JOIN central_alarmes ca ON ca.id = am.alarme_id LEFT JOIN locais l ON l.alarme_conta = ca.conta WHERE am.id = ? LIMIT 1", [$manutencaoAlarmeId]);
        if ($check['status'] !== 'success' || empty($check['data'])) {
            ApiResponse::error('VALIDATION_ERROR', 'Manutenção de alarme não encontrada.');
        }
        $targetSecretariaId = (int)($check['data'][0]->secretaria_id ?? 0);
    }

    if ($usuarioNivel !== 'admin' && $usuarioSecretariaId !== null && $targetSecretariaId > 0 && $targetSecretariaId !== $usuarioSecretariaId) {
        ApiResponse::error('FORBIDDEN', 'Você não tem permissão para anexar arquivos a este registro.');
    }

    $tipoPermitido = ['foto', 'documento', 'anexo'];
    if (!in_array($tipo, $tipoPermitido, true)) {
        ApiResponse::error('VALIDATION_ERROR', 'Tipo inválido. Use: foto, documento, anexo.');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['file']['error'] ?? -1;
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL => 'Upload foi feito parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão.',
        ];
        $msg = $messages[$errorCode] ?? 'Erro desconhecido no upload.';
        ApiResponse::error('BAD_REQUEST', $msg);
    }

    $file = $_FILES['file'];
    $nomeOriginalRaw = pathinfo($file['name'], PATHINFO_FILENAME);
    $nomeOriginalExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nomeOriginalSafe = preg_replace('/[^\x20-\x7E]/', '', $nomeOriginalRaw);
    $nomeOriginalSafe = preg_replace('/[<>"\/\\\\|:?*]/', '_', $nomeOriginalSafe);
    $nomeOriginalSafe = substr(trim($nomeOriginalSafe), 0, 100);
    if ($nomeOriginalExt !== '') {
        $nomeOriginalExt = preg_replace('/[^A-Za-z0-9._-]/', '', $nomeOriginalExt);
        $nomeOriginalSanitizado = $nomeOriginalSafe . '.' . $nomeOriginalExt;
    } else {
        $nomeOriginalSanitizado = $nomeOriginalSafe;
    }

    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        ApiResponse::error('BAD_REQUEST', 'Arquivo muito grande. Máximo: 10MB.');
    }

    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($detectedMime, $allowedMimes, true)) {
        ApiResponse::error('VALIDATION_ERROR', 'Tipo de arquivo não permitido. Use imagens, PDF ou documentos Office.');
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    $ext = $extMap[$detectedMime] ?? 'bin';
    $hash = bin2hex(random_bytes(16));
    $nomeArquivo = $hash . '.' . $ext;

    $subdir = 'equipamentos';
    if ($alarmeId) {
        $subdir = 'alarmes';
    }
    if ($manutencaoCameraId) {
        $subdir = 'manutencoes_cameras';
    }
    if ($manutencaoAlarmeId) {
        $subdir = 'manutencoes_alarmes';
    }
    $uploadDir = APP_ROOT . '/storage/uploads/' . $subdir . '/';
    $caminhoAbsoluto = $uploadDir . $nomeArquivo;

    // Flag: se consegue salvar em FS (storage/), usa o FS; senão guardará no BD.
    $salvarEmDisco = is_dir($uploadDir) && is_writable($uploadDir);
    if (!$salvarEmDisco) {
        if (!is_dir($uploadDir)) {
            $salvarEmDisco = @mkdir($uploadDir, 0700, true) && is_writable($uploadDir);
        }
        if (!$salvarEmDisco) {
            // Sem FS writable — guardar no BD
            $uploadDir = null;
        }
    }

    $conteudoBinario = null;
    $thumbnailPaths = [];

    $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    if (in_array($detectedMime, $imageMimes, true)) {
        $gdLoaders = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/gif'  => 'imagecreatefromgif',
            'image/webp' => 'imagecreatefromwebp',
            'image/bmp'  => 'imagecreatefrombmp',
        ];
        $gdSavers = [
            'image/jpeg' => 'imagejpeg',
            'image/png'  => 'imagepng',
            'image/gif'  => 'imagegif',
            'image/webp' => 'imagewebp',
            'image/bmp'  => 'imagebmp',
        ];
        $loader = $gdLoaders[$detectedMime];
        $saver = $gdSavers[$detectedMime];
        $src = @$loader($file['tmp_name']);
        if ($src === false) {
            ApiResponse::error('VALIDATION_ERROR', 'Imagem invalida ou corrompida.');
        }
        $quality = ($detectedMime === 'image/jpeg') ? 90 : -1;
        if ($salvarEmDisco) {
            // Salvar diretamente no FS
            $saved = @$saver($src, $caminhoAbsoluto, $quality);
            if (!$saved) {
                @imagedestroy($src);
                ApiResponse::internalError('Falha ao salvar imagem no disco.');
            }
        } else {
            // Salvar em memoria (buffer) para BD
            $tmpPath = tempnam(sys_get_temp_dir(), 'anexo_');
            $saved = @$saver($src, $tmpPath, $quality);
            @imagedestroy($src);
            if (!$saved) {
                @unlink($tmpPath);
                ApiResponse::internalError('Falha ao processar imagem (memoria).');
            }
            $conteudoBinario = file_get_contents($tmpPath);
            @unlink($tmpPath);
            if ($conteudoBinario === false) {
                ApiResponse::internalError('Falha ao ler imagem processada.');
            }
            // Atualiza tamanho real apos reprocessamento
            $file['size'] = strlen($conteudoBinario);
        }
        if (is_resource($src) || (is_object($src) && get_class($src) === 'GdImage')) {
            @imagedestroy($src);
        }

        $thumbnailPaths = [];
        if ($salvarEmDisco) {
            $thumbnailPaths = generateThumbnails($caminhoAbsoluto, $detectedMime);
        }
    } else {
        $headerValidators = [
            'application/msword' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1",
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => "\x50\x4B\x03\x04",
            'application/vnd.ms-excel' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => "\x50\x4B\x03\x04",
        ];
        $expectedHeader = $headerValidators[$detectedMime] ?? null;
        if ($expectedHeader !== null) {
            $fh = @fopen($file['tmp_name'], 'rb');
            $actualHeader = $fh !== false ? @fread($fh, strlen($expectedHeader)) : '';
            if ($fh !== false) {
                @fclose($fh);
            }
            if ($actualHeader !== $expectedHeader) {
                ApiResponse::error('VALIDATION_ERROR', 'Arquivo invalido ou corrompido.');
            }
        }
        if ($salvarEmDisco) {
            if (!move_uploaded_file($file['tmp_name'], $caminhoAbsoluto)) {
                ApiResponse::internalError('Falha ao salvar arquivo no servidor.');
            }
        } else {
            // Conteudo para BD
            $conteudoBinario = file_get_contents($file['tmp_name']);
            if ($conteudoBinario === false) {
                ApiResponse::internalError('Falha ao ler arquivo para armazenamento.');
            }
        }
    }

    $caminhoRelativo = 'uploads/' . $subdir . '/' . $nomeArquivo;
    $anexoId = 0;

    // Comprimir conteudo binario se valer a pena ( so se conteudo em BD e < 1MB para evitar pico de memoria )
    $conteudoComprimido = 0;
    if ($conteudoBinario !== null && function_exists('gzdeflate') && strlen($conteudoBinario) < 1048576) {
        $compressed = @gzdeflate($conteudoBinario, 6);
        if ($compressed !== false && strlen($compressed) < strlen($conteudoBinario)) {
            $conteudoBinario = $compressed;
            $conteudoComprimido = 1;
        }
    }

    $db->beginTransaction();
    try {
        $insertData = [
            'equipamento_id' => $equipamentoId,
            'alarme_id' => $alarmeId,
            'manutencao_camera_id' => $manutencaoCameraId,
            'manutencao_alarme_id' => $manutencaoAlarmeId,
            'tipo' => $tipo,
            'nome_original' => $nomeOriginalSanitizado,
            'nome_arquivo' => $nomeArquivo,
            'caminho' => $caminhoRelativo,
            'mime_type' => $detectedMime,
            'tamanho' => $file['size'],
            'descricao' => $descricao ?: null,
            'created_by' => currentUserId(),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($conteudoBinario !== null) {
            $insertData['conteudo'] = $conteudoBinario;
            $insertData['conteudo_comprimido'] = $conteudoComprimido;
        }
        if (!empty($thumbnailPaths)) {
            $insertData['thumbnails'] = json_encode($thumbnailPaths);
        }

        $result = $db->insert('equipamentos_anexos', $insertData);

        if ($result['status'] !== 'success') {
            throw new RuntimeException('Falha ao inserir anexo no banco.');
        }

        $anexoId = (int)$db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        if ($salvarEmDisco && is_file($caminhoAbsoluto)) {
            @unlink($caminhoAbsoluto);
        }
        foreach ($thumbnailPaths as $thumbPath) {
            if (is_file($thumbPath)) {
                @unlink($thumbPath);
            }
        }
        error_log('Erro ao inserir anexo: ' . $e->getMessage());
        ApiResponse::internalError('Erro ao registrar anexo no banco de dados.');
    }

    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    ApiResponse::created([
        'message' => 'Arquivo enviado com sucesso.',
        'anexo' => [
            'id' => $anexoId,
            'nome_original' => $nomeOriginalSanitizado,
            'nome_arquivo' => $nomeArquivo,
            'url' => $baseUrl . '/' . $caminhoRelativo,
            'tamanho' => $file['size'],
            'mime_type' => $detectedMime,
            'tipo' => $tipo,
            'descricao' => $descricao,
        ],
    ], $anexoId);
} catch (Throwable $e) {
    error_log('[api_upload_anexo] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao fazer upload.');
}
