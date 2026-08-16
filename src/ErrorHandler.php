<?php

declare(strict_types=1);

function registerGlobalErrorHandlers(string $context = 'web'): void
{
    static $registered = null;
    if ($registered !== null) {
        return;
    }
    $registered = $context;
    set_error_handler(static function (
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $exception) use ($context): void {
        logError($exception->getMessage(), [
            'context' => $context,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ], $context);

        // Report to Sentry in production
        if (function_exists('captureSentryException')) {
            captureSentryException($exception, ['context' => $context]);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($context === 'api') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'error' => 'Erro interno do servidor.',
                'message' => 'Erro interno do servidor.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        if (file_exists(__DIR__ . '/../resources/500.php')) {
            include __DIR__ . '/../resources/500.php';
        } else {
            echo '<div class="container mt-5 text-center"><h2>Erro interno do servidor.</h2></div>';
        }
        exit;
    });

    register_shutdown_function(static function () use ($context): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR
        ];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        // Clear any existing output buffers to avoid corrupting JSON response
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        logError($error['message'], [
            'context' => $context,
            'type' => 'FATAL',
            'file' => $error['file'],
            'line' => $error['line'],
        ], $context);

        if (headers_sent()) {
            exit(1);
        }

        http_response_code(500);

        if ($context === 'api') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'error' => 'Erro interno do servidor.',
                'message' => 'Erro interno do servidor.'
            ], JSON_UNESCAPED_UNICODE);
            exit(1);
        }

        header('Content-Type: text/html; charset=UTF-8');
        if (file_exists(__DIR__ . '/../resources/500.php')) {
            include __DIR__ . '/../resources/500.php';
        } else {
            echo 'Erro interno do servidor.';
        }
        exit(1);
    });
}
