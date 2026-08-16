<?php

declare(strict_types=1);

use App\Logging\LoggerFactory;

if (!function_exists('logger')) {
    function logger(string $channel = 'app'): \Monolog\Logger
    {
        return LoggerFactory::get($channel);
    }
}

if (!function_exists('logError')) {
    function logError(string $message, array $context = [], string $channel = 'app'): void
    {
        logger($channel)->error($message, $context);
    }
}

if (!function_exists('logInfo')) {
    function logInfo(string $message, array $context = [], string $channel = 'app'): void
    {
        logger($channel)->info($message, $context);
    }
}

if (!function_exists('logWarning')) {
    function logWarning(string $message, array $context = [], string $channel = 'app'): void
    {
        logger($channel)->warning($message, $context);
    }
}
