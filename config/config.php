<?php

function loadEnvFile(string $envPath): bool
{
    if (!is_file($envPath) || !is_readable($envPath)) {
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return false;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if (
            (getenv($key) !== false && getenv($key) !== '') ||
            (isset($_ENV[$key]) && $_ENV[$key] !== '') ||
            (isset($_SERVER[$key]) && $_SERVER[$key] !== '')
        ) {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if (!putenv($key . '=' . $value)) {
            error_log("Failed to set env: {$key}");
            continue;
        }
        $_ENV[$key] = $value;
    }

    return true;
}

function getEnvPath(): string
{
    $os = PHP_OS_FAMILY;
    if ($os === 'Windows') {
        return 'C:\env\sistema-cameras\.env';
    }
    return '/etc/sistema-cameras/.env';
}

function getCameraEnv(): string
{
    return getenv('CAMERAS_ENV') ?: 'development';
}

function isProduction(): bool
{
    return getCameraEnv() === 'production';
}

function loadConfiguration(): void
{
    $envPath = getEnvPath();
    if (!loadEnvFile($envPath)) {
        $samplePath = dirname(__DIR__) . '/.env.example';
        $msg = sprintf(
            "Arquivo de configuração não encontrado.\n\n" .
            "Crie o arquivo de configuração em:\n  %s\n\n" .
            "Copie o conteúdo de .env.example para começar.\n\n" .
            "Documentação: https://github.com/seu-projeto/docs/SETUP.md",
            $envPath
        );
        error_log('[CONFIG] Arquivo de configuração não encontrado: ' . $envPath);
        throw new RuntimeException($msg);
    }
}

loadConfiguration();

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}
setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR.utf8', 'pt_BR', 'Portuguese_Brazil.1252');
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', getCameraEnv());
}

$envDbHost = getenv('DB_HOST');
$envDbName = getenv('DB_NAME');
$envDbUser = getenv('DB_USER');
$envDbPass = getenv('DB_PASS');

$missingVars = [];
if ($envDbHost === false || $envDbHost === '') {
    $missingVars[] = 'DB_HOST';
}
if ($envDbName === false || $envDbName === '') {
    $missingVars[] = 'DB_NAME';
}
if ($envDbUser === false || $envDbUser === '') {
    $missingVars[] = 'DB_USER';
}
if ($envDbPass === false || $envDbPass === '') {
    $missingVars[] = 'DB_PASS';
}

if (!empty($missingVars)) {
    $msg = 'Configurações ausentes: ' . implode(', ', $missingVars);
    error_log('[CONFIG] ' . $msg);
    throw new RuntimeException($msg);
}

define('DB_HOST', $envDbHost);
define('DB_NAME', $envDbName);
define('DB_USER', $envDbUser);
define('DB_PASS', $envDbPass);

if (!defined('PROXY_TRUSTED_IPS')) {
    define('PROXY_TRUSTED_IPS', getenv('PROXY_TRUSTED_IPS') ?: '');
}
if (!defined('APP_ALLOWED_ORIGINS')) {
    define('APP_ALLOWED_ORIGINS', getenv('APP_ALLOWED_ORIGINS') ?: '');
}