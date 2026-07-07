<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ACCESS_LEVEL_ADMIN', 1);
define('ACCESS_LEVEL_SUPERVISOR', 2);
define('ACCESS_LEVEL_USER', 3);

define('CSRF_TOKEN_BYTES', 32);
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_SECONDS', 900);
define('LOGIN_LOCK_SECONDS', 900);

define('CSRF_TOKEN_WINDOW_SIZE', 3);
define('CSRF_FORM_TTL_SECONDS', 180);

function getCsrfToken(): string
{
    $tokens = $_SESSION['csrf_tokens'] ?? [];
    $current = null;
    if (!empty($tokens)) {
        $current = $tokens[array_key_last($tokens)] ?? null;
    }
    if (!$current) {
        $current = bin2hex(random_bytes(CSRF_TOKEN_BYTES));
        $_SESSION['csrf_tokens'] = [$current];
    }

    return $current;
}

function validateCsrfToken(?string $token): bool
{
    if (!$token) {
        return false;
    }

    $tokens = $_SESSION['csrf_tokens'] ?? [];
    $validations = $_SESSION['csrf_token_validations'] ?? [];
    $now = time();
    foreach ($validations as $tok => $ts) {
        if ($ts + CSRF_FORM_TTL_SECONDS < $now) {
            unset($validations[$tok]);
        }
    }
    foreach ($tokens as $i => $t) {
        if (hash_equals($t, $token)) {
            $validations[$t] = $validations[$t] ?? $now;
            $_SESSION['csrf_token_validations'] = $validations;
            return true;
        }
    }
    // Form HTML: token pode ja ter sido consumido por AJAX mas ainda dentro do TTL
    if (isset($validations[$token]) && $validations[$token] + CSRF_FORM_TTL_SECONDS >= $now) {
        $_SESSION['csrf_token_validations'] = $validations;
        return true;
    }
    $_SESSION['csrf_token_validations'] = $validations;

    return false;
}

function rotateCsrfToken(): void
{
    $tokens = $_SESSION['csrf_tokens'] ?? [];
    $newToken = bin2hex(random_bytes(CSRF_TOKEN_BYTES));
    $tokens[] = $newToken;
    if (count($tokens) > CSRF_TOKEN_WINDOW_SIZE) {
        array_shift($tokens);
    }
    $_SESSION['csrf_tokens'] = array_values($tokens);
}

function requireApiCsrf(bool $rotate = true): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
    if (in_array(strtoupper($method), $safeMethods, true)) {
        $tokens = $_SESSION['csrf_tokens'] ?? [];
        $current = $tokens[array_key_last($tokens)] ?? '';
        if ($current === '') {
            $current = bin2hex(random_bytes(CSRF_TOKEN_BYTES));
            $_SESSION['csrf_tokens'] = [$current];
        }
        header('X-CSRF-Token: ' . $current);
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($token)) {
        error_log('[CSRF] Falha na validacao. Token: ' . substr($token, 0, 16) . '... | Session keys: ' . implode(',', array_keys($_SESSION['csrf_tokens'] ?? [])) . ' | Method: ' . $_SERVER['REQUEST_METHOD']);
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
            'message' => 'Token CSRF invalido ou ausente.',
            'data' => [],
            'meta' => [
                'timestamp' => date('Y-m-d\TH:i:s\Z'),
                'request_id' => method_exists('ApiResponse', 'getRequestId') ? ApiResponse::getRequestId() : 'unknown'
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // AJAX: consome token imediatamente (rotaciona)
    // Mantem em csrf_token_validations para form HTML still-validate within TTL
    $tokens = $_SESSION['csrf_tokens'] ?? [];
    foreach ($tokens as $i => $t) {
        if (hash_equals($t, $token)) {
            unset($_SESSION['csrf_tokens'][$i]);
            $_SESSION['csrf_tokens'] = array_values($_SESSION['csrf_tokens']);
            break;
        }
    }
    // (nao limpa validations[$token] — form HTML ainda pode revalidar dentro do TTL)

    if ($rotate) {
        rotateCsrfToken();
    }

    $tokens = $_SESSION['csrf_tokens'] ?? [];
    if (empty($tokens)) {
        $tokens = [bin2hex(random_bytes(CSRF_TOKEN_BYTES))];
        $_SESSION['csrf_tokens'] = $tokens;
    }
    $current = $tokens[array_key_last($tokens)] ?? '';
    header('X-CSRF-Token: ' . $current);
}

function currentUserId(): ?int
{
    if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']->id)) {
        return null;
    }

    return (int) $_SESSION['usuario']->id;
}

/**
 * Resolve o IP real do cliente considerando proxies confiáveis.
 * Usa REMOTE_ADDR como fonte primária. Só confia em X-Forwarded-For
 * se a conexão direta vier de um proxy listado em PROXY_TRUSTED_IPS.
 */
function resolveClientIp(): string
{
    $remoteAddr = trim($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    // Normalizar IPv6 localhost
    if ($remoteAddr === '::1') {
        $remoteAddr = '127.0.0.1';
    }

    if (defined('PROXY_TRUSTED_IPS') && PROXY_TRUSTED_IPS !== '') {
        $trustedProxies = array_map('trim', explode(',', PROXY_TRUSTED_IPS));
        if (in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                $ips = array_reverse(array_map('trim', explode(',', $forwarded)));
                foreach ($ips as $ip) {
                    if (!in_array($ip, $trustedProxies, true) && filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
            $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
            if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }
    }

    return $remoteAddr;
}

function userHasAccess(string $required = 'user'): bool
{
    if (!isset($_SESSION['usuario'])) {
        return false;
    }

    $userLevel = resolveUserAccessLevel($_SESSION['usuario']);
    $hierarchy = ['user', 'supervisor', 'admin'];

    $userRank = array_search($userLevel, $hierarchy, true);
    $requiredRank = array_search($required, $hierarchy, true);

    return $userRank !== false && $requiredRank !== false && $userRank >= $requiredRank;
}

function normalizeAccessLevelName(?string $name): ?string
{
    if ($name === null) {
        return null;
    }

    $normalized = strtolower(trim($name));
    if (in_array($normalized, ['admin', 'supervisor', 'user'], true)) {
        return $normalized;
    }

    return null;
}

function mapAccessIdToName($id): ?string
{
    if ($id === null || $id === '') {
        return null;
    }

    // DB: admin=1, supervisor=2, user=3
    $map = [
        1 => 'admin',
        2 => 'supervisor',
        3 => 'user',
    ];

    $intId = (int) $id;
    return $map[$intId] ?? null;
}

function resolveUserAccessLevel($user): string
{
    if (!$user) {
        return 'user';
    }

    if (isset($user->nivel_acesso)) {
        $normalized = normalizeAccessLevelName((string) $user->nivel_acesso);
        if ($normalized !== null) {
            return $normalized;
        }
    }

    if (isset($user->nivel_acesso_id)) {
        $fromId = mapAccessIdToName($user->nivel_acesso_id);
        if ($fromId !== null) {
            return $fromId;
        }
    }

    return 'user';
}

function ensureSessionUserAccessLevel(): void
{
    if (!isset($_SESSION['usuario'])) {
        return;
    }

    $level = resolveUserAccessLevel($_SESSION['usuario']);
    if (!isset($_SESSION['usuario']->nivel_acesso) || $_SESSION['usuario']->nivel_acesso !== $level) {
        $_SESSION['usuario']->nivel_acesso = $level;
    }
}

ensureSessionUserAccessLevel();

function validatePasswordPolicy(string $password, ?array &$errors = null): bool
{
    $errors = [];
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
    }
    if (preg_match('/^\d+$/', $password)) {
        $errors[] = 'A senha nao pode conter apenas numeros.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos uma letra minuscula.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos uma letra maiuscula.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'A senha deve conter pelo menos um numero.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos um caractere especial (!@#$% etc).';
    }

    return empty($errors);
}

function enableAuditTriggers($db, string $origem = 'web'): void
{
    $userId = currentUserId();
    $db->setUserContext($userId, $origem);
}

function auditEvent($db, string $entidade, ?int $entidadeId, string $operacao, ?array $dadosAntes = null, ?array $dadosDepois = null, string $origem = 'web'): void
{
    try {
        $usuarioId = currentUserId();
        $mapaOperacao = ['INSERT' => 1, 'UPDATE' => 2, 'DELETE' => 3];
        $operacaoId = $mapaOperacao[strtoupper($operacao)] ?? null;
        if ($operacaoId === null) {
            error_log('Falha ao registrar auditoria: operacao desconhecida "' . $operacao . '"');
            return;
        }
        $db->query(
            "INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, origem, changed_by, created_at)
             VALUES (:entidade, :entidade_id, :operacao_id, :dados_antes, :dados_depois, :origem, :changed_by, NOW())",
            [
                ':entidade' => $entidade,
                ':entidade_id' => $entidadeId,
                ':operacao_id' => $operacaoId,
                ':dados_antes' => $dadosAntes ? json_encode($dadosAntes, JSON_UNESCAPED_UNICODE) : null,
                ':dados_depois' => $dadosDepois ? json_encode($dadosDepois, JSON_UNESCAPED_UNICODE) : null,
                ':origem' => $origem,
                ':changed_by' => $usuarioId,
            ]
        );
    } catch (Throwable $e) {
        error_log('Falha ao registrar auditoria: ' . $e->getMessage());
        writeAuditLog($entidade, $entidadeId, $operacao, $dadosAntes, $dadosDepois, $origem);
    }
}

/**
 * Fallback: escrever auditoria em arquivo quando DB está indisponível
 */
function writeAuditLog(string $entidade, ?int $entidadeId, string $operacao, ?array $dadosAntes = null, ?array $dadosDepois = null, string $origem = 'web'): void
{
    try {
        $logDir = defined('AUDIT_LOG_DIR') ? AUDIT_LOG_DIR : (ini_get('error_log') ? dirname(ini_get('error_log')) : sys_get_temp_dir());
        if (strpos(str_replace('\\', '/', realpath($logDir) ?: $logDir), str_replace('\\', '/', APP_ROOT . '/public')) === 0) {
            $logDir = sys_get_temp_dir() . '/sistema-cameras-audit';
        }
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0700, true);
        }
        $logFile = rtrim($logDir, '/') . '/audit_' . date('Y-m-d') . '.log';
        $entry = json_encode([
            'timestamp' => date('Y-m-d\TH:i:sP'),
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'operacao' => $operacao,
            'dados_antes' => $dadosAntes,
            'dados_depois' => $dadosDepois,
            'origem' => $origem,
            'changed_by' => currentUserId(),
        ], JSON_UNESCAPED_UNICODE);
        file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $e2) {
        error_log('Falha ao escrever auditoria em arquivo: ' . $e2->getMessage());
    }
}

function generateTemporaryPassword(int $length = 12): string
{
    $length = max(PASSWORD_MIN_LENGTH, $length);
    $lower  = 'abcdefghijkmnopqrstuvwxyz';
    $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $special = '!@#$%&';
    $all = $lower . $upper . $digits . $special;
    $allLen = strlen($all);
    $max = 256 - (256 % $allLen);

    $password = '';
    // guarantee at least one from each category
    $password .= $lower[random_int(0, strlen($lower) - 1)];
    $password .= $upper[random_int(0, strlen($upper) - 1)];
    $password .= $digits[random_int(0, strlen($digits) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];

    while (strlen($password) < $length) {
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($bytes[$i]);
            if ($byte < $max) {
                $password .= $all[$byte % $allLen];
                if (strlen($password) >= $length) break;
            }
        }
    }

    // shuffle to avoid predictable prefix
    $password = str_split($password);
    for ($i = count($password) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$password[$i], $password[$j]] = [$password[$j], $password[$i]];
    }
    return implode('', $password);
}

// Funções centralizadas de segurança de senha
function hashPasswordOptions(): array
{
    if (defined('PASSWORD_ARGON2ID')) {
        $memoryCost = (int)(getenv('ARGON2_MEMORY_COST') ?: 65536);
        $timeCost   = (int)(getenv('ARGON2_TIME_COST')   ?: 4);
        $threads    = (int)(getenv('ARGON2_THREADS')     ?: 3);
        return [
            'algo' => PASSWORD_ARGON2ID,
            'options' => [
                'memory_cost' => max(8192, min($memoryCost, 262144)),
                'time_cost'   => max(2, min($timeCost, 10)),
                'threads'     => max(1, min($threads, 8)),
            ],
        ];
    }
    $cost = (int)(getenv('BCRYPT_COST') ?: 12);
    if ($cost < 8 || $cost > 14) {
        $cost = 12;
    }
    return [
        'algo' => PASSWORD_BCRYPT,
        'options' => ['cost' => $cost],
    ];
}

function hashPassword(string $password): string
{
    $config = hashPasswordOptions();
    return password_hash($password, $config['algo'], $config['options']);
}

function verifyPassword(string $password, string $hash, ?string &$newHash = null): bool
{
    if (!password_verify($password, $hash)) {
        return false;
    }
    $config = hashPasswordOptions();
    if (password_needs_rehash($hash, $config['algo'], $config['options'])) {
        $newHash = password_hash($password, $config['algo'], $config['options']);
    }
    return true;
}
