<?php

if (!defined('API_ROOT')) {
    define('API_ROOT', __DIR__);
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

require_once APP_ROOT . '/vendor/autoload.php';

require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/inc/security.php';
require_once APP_ROOT . '/inc/check_access.php';
require_once APP_ROOT . '/inc/single_session.php';
require_once APP_ROOT . '/src/ErrorHandler.php';

function requiredAccessForApiEndpoint(string $endpoint): string
{
    $adminEndpoints = [
        'auditoria_cameras',
        'limpar_sessoes_orfas',
        'health',
        'api_health',
    ];

    $supervisorEndpoints = [
        'cadastrar_alarmes',
        'cadastrar_cameras',
        'editar_alarme',
        'editar_camera',
        'excluir_alarme',
        'excluir_anexo',
        'excluir_camera',
        'exportar_locais',
        'notificacoes',
        'relatorios_cameras',
        'upload_anexo',
    ];

    if (in_array($endpoint, $adminEndpoints, true)) {
        return 'admin';
    }

    if (in_array($endpoint, $supervisorEndpoints, true)) {
        return 'supervisor';
    }

    return 'user';
}

function requireApiAccessForEndpoint(string $endpoint): void
{
    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $requiredAccess = requiredAccessForApiEndpoint($endpoint);
    if (!userHasAccess($requiredAccess)) {
        ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
    }
}

function requireApiAuth(string $minAccess = 'user', bool $writeClose = false): void
{
    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    if (!userHasAccess($minAccess)) {
        ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
    }

    if ($writeClose) {
        session_write_close();
    }
}

function executeApiRequest(?string $endpointOverride = null): void
{
    registerGlobalErrorHandlers('api');

    $version = 'v2';

    $page = $endpointOverride ?? $_GET['page'] ?? '';
    $apiFile = basename(str_replace('api/', '', $page));
    $apiFile = preg_replace('/^api_/', '', $apiFile);
    $endpoint = $apiFile;

    $allowedEndpoints = [
        'alarmes', 'auditoria_cameras', 'cadastrar_alarmes', 'cadastrar_cameras',
        'cameras', 'cep_lookup', 'dashboard',         'editar_alarme', 'editar_camera',
        'excluir_alarme', 'excluir_anexo', 'excluir_camera', 'exportar_locais', 'geocode', 'get_modelos', 'health',
        'limpar_sessoes_orfas', 'listar_anexos', 'locais', 'manutencao_alarmes',
        'manutencao_cameras', 'manutencao_utils', 'modelos_alarmes', 'modelos_cameras',
        'notificacoes',
        'ping', 'relatorios_cameras', 'renovar_sessao', 'servir_anexo', 'session_check',
        'status', 'swagger', 'upload_anexo',
    ];

    if (!in_array($endpoint, $allowedEndpoints, true)) {
        ApiResponse::notFound('endpoint', $endpoint);
    }

    $filepath = API_ROOT . "/{$version}/api_{$apiFile}.php";

    if (!file_exists($filepath)) {
        $filepathAlt = API_ROOT . "/{$version}/{$apiFile}.php";
        if (file_exists($filepathAlt)) {
            $filepath = $filepathAlt;
        }
    }

    $exists = file_exists($filepath);

    $isGet = $_SERVER['REQUEST_METHOD'] === 'GET';

    $rateLimiter = new RateLimiter(
        storagePath: APP_ROOT . '/storage/ratelimit',
        strategy: $isGet ? 'token_bucket' : 'sliding_window',
        configs: [
            '/^auth:/' => [10, 60],
            '/^login:/' => [5, 60],
            '/^upload_anexo:/' => [10, 60],
            '/^geocode:/' => [5, 60],
            '/^POST:/' => [30, 60],
            '/^PUT:/' => [30, 60],
            '/^DELETE:/' => [20, 60],
            '/^GET:/' => [120, 60],
        ]
    );

    if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
        $clientIp = resolveClientIp();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rateLimitKey = "{$endpoint}:{$clientIp}";

        if (!$rateLimiter->consume($rateLimitKey)) {
            ApiResponse::rateLimited($rateLimiter->getRetryAfter($rateLimitKey));
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-API-Version: v2');
    header('X-Request-ID: ' . ApiResponse::getRequestId());
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $idempotentGetEndpoints = [
        'ping', 'status', 'modelos_cameras', 'modelos_alarmes',
        'locais', 'dashboard', 'cameras', 'alarmes',
        'notificacoes', 'relatorios_cameras', 'listar_anexos',
        'servir_anexo', 'auditoria_cameras', 'health',
    ];
    $isIdempotentGet = ($_SERVER['REQUEST_METHOD'] === 'GET')
        && in_array($endpoint, $idempotentGetEndpoints, true);

    if ($isIdempotentGet) {
        header('Cache-Control: private, max-age=10, must-revalidate');
    } else {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    header('Pragma: no-cache');
    header("Content-Security-Policy: default-src 'none'; script-src 'none'; style-src 'none'; connect-src 'self'; form-action 'none'; frame-ancestors 'none'; base-uri 'none'");
    // HSTS - Em produção, sempre enviar se configurado (mesmo atrás de proxy)
    if (isProduction() || isHttpsRequest()) {
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
    }

    $allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $isOriginAllowed = false;

    $hasWildcard = false;
    if (defined('APP_ALLOWED_ORIGINS') && APP_ALLOWED_ORIGINS !== '') {
        $configuredOrigins = array_map('trim', explode(',', APP_ALLOWED_ORIGINS));
        if (in_array('*', $configuredOrigins, true)) {
            $hasWildcard = true;
            $isOriginAllowed = true;
            if (empty($allowedOrigin)) {
                logWarning('APP_ALLOWED_ORIGINS contem "*" — Access-Control-Allow-Credentials nao sera enviado, mas restrinja a origens especificas para seguranca.', [], 'security');
            }
        } else {
            $isOriginAllowed = in_array($allowedOrigin, $configuredOrigins, true);
        }
    } elseif (!empty($allowedOrigin)) {
        $serverScheme = isHttpsRequest() ? 'https' : 'http';
        $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        if ($serverHost === '' || preg_match('/[<>(){}|\\^~\[\]`\x00-\x1f\x7f]/', $serverHost)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid host']);
            exit;
        }
        $serverPort = (string)($_SERVER['SERVER_PORT'] ?? '80');
        $defaultPort = ($serverScheme === 'https') ? '443' : '80';
        $expectedOrigin = ($serverPort === $defaultPort)
            ? "{$serverScheme}://{$serverHost}"
            : "{$serverScheme}://{$serverHost}:{$serverPort}";
        $isOriginAllowed = ($allowedOrigin === $expectedOrigin);
    }

    header('Vary: Origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (!$isOriginAllowed) {
            http_response_code(204);
            exit;
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Version, X-Request-ID');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }

    if (!$isOriginAllowed && !empty($allowedOrigin)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cross-origin requests not allowed']);
        exit;
    }

    if ($isOriginAllowed && !empty($allowedOrigin)) {
        if ($hasWildcard) {
            header('Access-Control-Allow-Origin: *');
        } else {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            header('Access-Control-Allow-Credentials: true');
        }
    }

    if (!$exists) {
        ApiResponse::notFound('endpoint', $endpoint);
    }

    $publicEndpoints = ['auth/login', 'auth/register', 'ping', 'api_ping'];
    $noRotateEndpoints = ['renovar_sessao'];
    $noCsrfEndpoints = [];
    $writeCloseEndpoints = [
        'alarmes', 'cameras', 'dashboard', 'cep_lookup', 'geocode',
        'locais', 'modelos_cameras', 'modelos_alarmes', 'notificacoes',
        'relatorios_cameras', 'servir_anexo', 'listar_anexos',
        'auditoria_cameras', 'status', 'ping',
    ];
    if (!in_array($endpoint, $publicEndpoints, true)) {
        requireApiAccessForEndpoint($endpoint);
    }
    if (isset($_SESSION['usuario']) && isset($_SESSION['usuario']->id)) {
        $db = db();
        $db->setUserContext((int)$_SESSION['usuario']->id, 'api');
    }
    $shouldRotate = !in_array($endpoint, $noRotateEndpoints, true);
    if (!in_array($endpoint, $noCsrfEndpoints, true)) {
        requireApiCsrf($shouldRotate);
    }

    if (in_array($endpoint, $writeCloseEndpoints, true) && !headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if ($exists && file_exists($filepath)) {
        include $filepath;
        if ($isIdempotentGet && !headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    } else {
        ApiResponse::notFound('endpoint', $endpoint);
    }
}
