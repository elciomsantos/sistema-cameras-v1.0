<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Exception\ServiceException;

trait ParsesInput
{
    public static function requireAccess(): void
    {
        requireApiAuth('supervisor');
    }

    public static function parseJsonInput(?string $raw = null): array
    {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 10 * 1024 * 1024) {
            throw ServiceException::badRequest('Payload muito grande. Maximo: 10MB.');
        }
        $raw ??= file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            throw ServiceException::badRequest('Nenhum dado recebido.');
        }
        $jsonInput = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ServiceException::badRequest('JSON invalido: ' . json_last_error_msg());
        }
        $data = is_array($jsonInput) ? $jsonInput : [];
        if (empty($data)) {
            throw ServiceException::badRequest('Nenhum dado recebido.');
        }
        return $data;
    }
}
