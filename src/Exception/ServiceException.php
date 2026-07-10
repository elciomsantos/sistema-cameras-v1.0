<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exceção para erros de negócio nos services.
 * Permite que o controller decida como responder (JSON, redirect, etc).
 */
class ServiceException extends \RuntimeException
{
    private string $errorCode;

    public function __construct(string $errorCode, string $message, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function badRequest(string $message): self
    {
        return new self('BAD_REQUEST', $message);
    }

    public static function validation(string $message): self
    {
        return new self('VALIDATION_ERROR', $message);
    }

    public static function notFound(string $resource, int|string $id = ''): self
    {
        $idPart = $id !== '' ? " {$id}" : '';
        return new self('NOT_FOUND', "{$resource}{$idPart} nao encontrado(a).");
    }

    public static function forbidden(string $reason = 'Acesso negado.'): self
    {
        return new self('FORBIDDEN', $reason);
    }

    public static function internal(string $message = 'Erro interno.'): self
    {
        return new self('INTERNAL_ERROR', $message);
    }
}
