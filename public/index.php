<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Garantir que APP_ROOT e API_ROOT estejam definidos para includes internos
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/..');
}

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../config/app.php';
configureSessionSecurity();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../inc/assets.php';
require_once __DIR__ . '/../inc/single_session.php';
require_once __DIR__ . '/../src/ErrorHandler.php';
require_once __DIR__ . '/../inc/session_handler.php';
registerGlobalErrorHandlers('web');
header('Content-Type: text/html; charset=UTF-8');

$tempo_maximo = SESSION_TIMEOUT;
$page = $_GET['page'] ?? 'home';

$publicBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($publicBasePath === '/') {
    $publicBasePath = '';
}
$APP_BASE_PATH = $publicBasePath;
$APP_PUBLIC_PATH = $publicBasePath;
if (!defined('BASE_URL')) {
    define('BASE_URL', $publicBasePath !== '' ? $publicBasePath : '');
}

$isProduction = defined('ENVIRONMENT') && ENVIRONMENT === 'production';
$scriptEvalPolicy = $isProduction ? "" : " 'unsafe-inline'";
$cspNonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
$CSP_NONCE = $cspNonce;

$inlineStylePolicy = (defined('CSP_ALLOW_INLINE_STYLES') && CSP_ALLOW_INLINE_STYLES) ? " 'unsafe-inline'" : '';
$cspPolicy = "default-src 'self'; "
    . "script-src 'self' 'nonce-{$cspNonce}'{$scriptEvalPolicy} https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net; "
    . "style-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net; "
    . "style-src-elem 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net; "
    . "style-src-attr{$inlineStylePolicy}; "
    . "img-src 'self' data: blob:; "
    . "font-src 'self' data: https://cdnjs.cloudflare.com; "
    . "connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net; "
    . "frame-ancestors 'self'; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'";
header('Content-Security-Policy: ' . $cspPolicy);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), midi=(), payment=()');
if ($isProduction || isHttpsRequest()) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
}

$isApi = str_starts_with($page, 'api/');

function resolvePageScriptPath(string $page): ?string
{
    $candidates = [
        __DIR__ . '/../auth/' . $page . '.php',
        __DIR__ . '/../accounts/' . $page . '.php',
        __DIR__ . '/../resources/' . $page . '.php',
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    return null;
}

// [ROUTING] Configuracao unificada de rotas
$routes = [

    // Logout (deve rodar antes do timeout)
    'logout'        => ['handler' => __DIR__ . '/../auth/action_logout.php', 'type' => 'logout'],
    'action_logout' => ['handler' => __DIR__ . '/../auth/action_logout.php', 'type' => 'logout'],

    // Action: sem layout, so processamento e redirect/exit
    'login_submit'         => ['handler' => __DIR__ . '/../auth/action_login_submit.php', 'type' => 'action'],
    'action_login_submit'  => ['handler' => __DIR__ . '/../auth/action_login_submit.php', 'type' => 'action'],
    'ativarUsuario'        => ['handler' => __DIR__ . '/../accounts/action_ativar_usuario.php', 'type' => 'action'],
    'action_ativar_usuario'=> ['handler' => __DIR__ . '/../accounts/action_ativar_usuario.php', 'type' => 'action'],
    'bloquearUsuario'      => ['handler' => __DIR__ . '/../accounts/action_bloquear_usuario.php', 'type' => 'action'],
    'action_bloquear_usuario'=> ['handler' => __DIR__ . '/../accounts/action_bloquear_usuario.php', 'type' => 'action'],
    'deletarUsuario'       => ['handler' => __DIR__ . '/../accounts/action_deletar_usuario.php', 'type' => 'action'],
    'action_deletar_usuario'=> ['handler' => __DIR__ . '/../accounts/action_deletar_usuario.php', 'type' => 'action'],
    'action_listar_locais' => ['handler' => __DIR__ . '/../resources/action_listar_locais.php', 'type' => 'action'],

    // Mixed: intercepta POST com submit, senao renderiza layout
    'recuperar_senha' => ['handler' => __DIR__ . '/../auth/action_recuperar_senha.php', 'type' => 'mixed'],
    'alterar_senha'   => ['handler' => __DIR__ . '/../accounts/action_alterar_senha.php', 'type' => 'mixed'],
    'editarUsuario'   => ['handler' => __DIR__ . '/../accounts/action_editar_usuario.php', 'type' => 'mixed'],
    'trocar_senha'    => ['handler' => __DIR__ . '/../accounts/action_trocar_senha.php', 'type' => 'mixed'],
    'cadastroUsuario' => ['handler' => __DIR__ . '/../accounts/action_cadastro_usuario.php', 'type' => 'mixed'],
    'cadastro_locais' => ['handler' => __DIR__ . '/../resources/action_cadastro_locais.php', 'type' => 'mixed'],
    'editar_locais'   => ['handler' => __DIR__ . '/../resources/action_editar_locais.php', 'type' => 'mixed'],

];

// [1] Logout antes do timeout (sempre limpar sessao)
if (isset($routes[$page]) && $routes[$page]['type'] === 'logout') {
    require_once $routes[$page]['handler'];
    exit;
}

// [2] Timeout, sessao unica, CSRF
$time_left = verificarTimeout($isApi, $tempo_maximo);
enforceUniqueSession($isApi);
getCsrfToken();

require_once __DIR__ . '/../config/config.php';
$pages_permitidas = require_once __DIR__ . '/../inc/pages.php';
require_once __DIR__ . '/../inc/check_access.php';

// [3] Redirecionar nao logados para login (exceto paginas publicas e API)
$publicPages = ['login', 'login_submit', 'action_login_submit', 'nao_autorizado'];
if (!isset($_SESSION['usuario']) && !in_array($page, $publicPages, true) && !str_starts_with($page, 'api/')) {
    $page = 'login';
}

// [4] Logado tentando login → home
if (isset($_SESSION['usuario']) && $page === 'login') {
    $page = 'home';
}

// [5] Whitelist de paginas
if (!in_array($page, $pages_permitidas, true)) {
    $page = '404';
}

// [6] API
if (str_starts_with($page, 'api/')) {
    $publicApiPages = [
        'api/api_ping',
    ];
    if (!isset($_SESSION['usuario']) && !in_array($page, $publicApiPages, true)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'error' => 'Sessao nao encontrada',
            'message' => 'Sessao nao encontrada'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    define('API_INCLUDED_FROM_INDEX', true);
    require_once __DIR__ . '/../api/bootstrap-api.php';

    executeApiRequest($page);
    exit;
}

// [7] Controle de acesso (exceto paginas publicas)
$pageAccess = require __DIR__ . '/../inc/access_rules.php';
if (!in_array($page, $publicPages, true)) {
    requererAcesso($pageAccess[$page] ?? 'user');
}

// [8] Dispatch de rotas: action e mixed
if (isset($routes[$page])) {
    $route = $routes[$page];

    // Action: carrega handler sem layout
    if ($route['type'] === 'action') {
        require_once $route['handler'];
        exit;
    }

    // Mixed: intercepta POST com submit
    if ($route['type'] === 'mixed' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
        require_once $route['handler'];
        exit;
    }

    // Mixed GET: continua para renderizacao normal abaixo
}

// [9] Resolver caminho do script da pagina
$scriptPath = resolvePageScriptPath($page);
if ($scriptPath === null) {
    $scriptPath = resolvePageScriptPath('404');
}
if ($scriptPath === null) {
    http_response_code(404);
    echo 'Pagina nao encontrada.';
    exit;
}

// [10] Renderizar com layout (buffering global ja ativo desde o inicio)
$CURRENT_PAGE = $page;
require_once __DIR__ . '/../inc/header.php';
require_once $scriptPath;
require_once __DIR__ . '/../inc/footer.php';

