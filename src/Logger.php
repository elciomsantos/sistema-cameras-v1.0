<?php

declare(strict_types=1);

class Logger
{
    private static ?string $correlationId = null;
    private static string $minLevel = 'debug';
    private static array $levels = ['debug', 'info', 'warning', 'error', 'critical'];

    public static function init(?string $correlationId = null): void
    {
        self::$correlationId = $correlationId ?? self::generateCorrelationId();
    }

    public static function getCorrelationId(): string
    {
        if (self::$correlationId === null) {
            self::$correlationId = self::generateCorrelationId();
        }
        return self::$correlationId;
    }

    public static function setMinLevel(string $level): void
    {
        if (in_array($level, self::$levels, true)) {
            self::$minLevel = $level;
        }
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        if (array_search($level, self::$levels, true) < array_search(self::$minLevel, self::$levels, true)) {
            return;
        }

        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'correlation_id' => self::getCorrelationId(),
            'message' => $message,
            'context' => $context,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
        error_log($line);
    }

    private static function generateCorrelationId(): string
    {
        return 'req_' . substr(bin2hex(random_bytes(12)), 0, 16);
    }
}
