<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Garantir que APP_ROOT e API_ROOT estejam definidos para includes internos
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/..');
}

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
require_once __DIR__ . '/../src/Services/LocaisService.php';
require_once __DIR__ . '/../src/Services/LookupService.php';
require_once __DIR__ . '/../src/Services/AlarmeService.php';
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
if ($isProduction && isHttpsRequest()) {
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

// Processar logout antes do timeout para sempre encerrar explicitamente
if ($page === 'action_logout' || $page === 'logout') {
    require_once __DIR__ . '/../auth/action_logout.php';
    exit;
}

// Verificar timeout uma unica vez (antes de qualquer outra logica)
$time_left = verificarTimeout($isApi, $tempo_maximo);
enforceUniqueSession($isApi);
getCsrfToken();

require_once __DIR__ . '/../config/config.php';
$pages_permitidas = require_once __DIR__ . '/../inc/pages.php';
require_once __DIR__ . '/../inc/check_access.php';

// Se nao estiver logado e nao for pagina de login_submit ou API, redireciona para login
$publicPages = ['login', 'login_submit', 'action_login_submit', 'nao_autorizado'];
if (!isset($_SESSION['usuario']) && !in_array($page, $publicPages, true) && !str_starts_with($page, 'api/')) {
    $page = 'login';
}

// Se esta logado e tenta acessar login, vai para home
if (isset($_SESSION['usuario']) && $page === 'login') {
    $page = 'home';
}

// Verifica se a pagina existe
if (!in_array($page, $pages_permitidas, true)) {
    $page = '404';
}

// Processamento de API
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

// Controle de acesso por pagina
$pageAccess = require __DIR__ . '/../inc/access_rules.php';

if (!in_array($page, $publicPages, true)) {
    requererAcesso($pageAccess[$page] ?? 'user');
}

$scriptPath = resolvePageScriptPath($page);
if ($scriptPath === null) {
    $scriptPath = resolvePageScriptPath('404');
}
if ($scriptPath === null) {
    http_response_code(404);
    echo 'Pagina nao encontrada.';
    exit;
}

// Endpoints de acao (redirect/processamento) nao devem renderizar layout.
$actionPages = [
    'login_submit',
    'action_login_submit',
    'ativarUsuario',
    'action_ativar_usuario',
    'bloquearUsuario',
    'action_bloquear_usuario',
    'deletarUsuario',
    'action_deletar_usuario',
    'action_listar_locais',
];
if (in_array($page, $actionPages, true)) {
    require_once $scriptPath;
    exit;
}

// Action handlers: interceptam POST para processar formularios e redirecionar
$postActionPages = [
    'recuperar_senha' => __DIR__ . '/../auth/action_recuperar_senha.php',
    'alterar_senha'   => __DIR__ . '/../accounts/action_alterar_senha.php',
    'editarUsuario'   => __DIR__ . '/../accounts/action_editar_usuario.php',
    'trocar_senha'    => __DIR__ . '/../accounts/action_trocar_senha.php',
    'cadastroUsuario' => __DIR__ . '/../accounts/action_cadastro_usuario.php',
    'cadastro_locais' => __DIR__ . '/../resources/action_cadastro_locais.php',
    'editar_locais'   => __DIR__ . '/../resources/action_editar_locais.php',

];
if (isset($postActionPages[$page]) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    require_once $postActionPages[$page];
    exit;
}

// Paginas mistas: podem redirecionar antes da renderizacao.
// Executamos antes do layout para preservar header('Location'),
// mas mantemos o HTML em buffer quando houver renderizacao normal.
$preRenderPages = [
    'cadastroUsuario',
    'trocar_senha',
    'alterar_senha',
    'editarUsuario',
];
$preRenderedContent = null;
if (in_array($page, $preRenderPages, true)) {
    ob_start();
    require_once $scriptPath;
    $preRenderedContent = ob_get_clean();
}

$CURRENT_PAGE = $page;
require_once __DIR__ . '/../inc/header.php';
if ($preRenderedContent !== null) {
    echo $preRenderedContent;
} else {
    require_once $scriptPath;
}

require_once __DIR__ . '/../inc/footer.php';

