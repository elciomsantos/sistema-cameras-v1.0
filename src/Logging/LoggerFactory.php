<?php

declare(strict_types=1);

class LoggerFactory
{
    private static array $instances = [];

    public static function create(string $channel = 'app', ?string $logDir = null): \Monolog\Logger
    {
        $logDir ??= self::resolveLogDir();
        $logger = new \Monolog\Logger($channel);

        $level = isProduction() ? \Monolog\Level::Info : \Monolog\Level::Debug;

        try {
            if ($logDir !== null && is_dir($logDir) && is_writable($logDir)) {
                $handler = new \Monolog\Handler\RotatingFileHandler(
                    $logDir . '/' . $channel . '.log',
                    30,
                    $level
                );
                $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
                $logger->pushHandler($handler);
            }
        } catch (\Throwable $e) {
            error_log('[LoggerFactory] Failed to create file handler: ' . $e->getMessage());
        }

        $logger->pushHandler(new \Monolog\Handler\ErrorLogHandler(
            \Monolog\Handler\ErrorLogHandler::OPERATING_SYSTEM,
            $level
        ));

        return $logger;
    }

    public static function get(string $channel = 'app', ?string $logDir = null): \Monolog\Logger
    {
        if (!isset(self::$instances[$channel])) {
            self::$instances[$channel] = self::create($channel, $logDir);
        }
        return self::$instances[$channel];
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    private static function resolveLogDir(): ?string
    {
        $paths = [
            defined('APP_ROOT') ? APP_ROOT . '/storage/logs' : null,
            dirname(__DIR__, 2) . '/storage/logs',
            sys_get_temp_dir() . '/sistema-cameras/logs',
        ];

        foreach ($paths as $path) {
            if ($path === null) continue;
            if (is_dir($path) && is_writable($path)) {
                return $path;
            }
        }

        foreach ($paths as $path) {
            if ($path === null) continue;
            try {
                if (!is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                if (is_writable($path)) {
                    return $path;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
