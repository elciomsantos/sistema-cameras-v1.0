<?php

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=login');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['error'] = 'Falha de validacao CSRF.';
    header('Location: index.php?page=login');
    exit;
}

$usuario = $_POST['text_usuario'] ?? null;
$senha = $_POST['text_senha'] ?? null;

if ($usuario !== null && mb_strlen($usuario) > 255) {
    $_SESSION['error'] = 'Falha de validacao.';
    header('Location: index.php?page=login');
    exit;
}
if ($senha !== null && mb_strlen($senha) > 128) {
    $_SESSION['error'] = 'Falha de validacao.';
    header('Location: index.php?page=login');
    exit;
}

if(!$usuario || !$senha) {
    header('Location: index.php?page=login');
    exit;
}

$now = time();
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (defined('PROXY_TRUSTED_IPS') && PROXY_TRUSTED_IPS !== '') {
    $trustedProxies = array_map('trim', explode(',', PROXY_TRUSTED_IPS));
    if (in_array($remoteAddr, $trustedProxies, true)) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $ips = array_reverse(array_map('trim', explode(',', $forwarded)));
            $ip = $remoteAddr;
            foreach ($ips as $forwardedIp) {
                if (!in_array($forwardedIp, $trustedProxies, true) && filter_var($forwardedIp, FILTER_VALIDATE_IP)) {
                    $ip = $forwardedIp;
                    break;
                }
            }
        } else {
            $ip = $remoteAddr;
        }
    } else {
        $ip = $remoteAddr;
    }
} else {
    $ip = $remoteAddr;
}

$maxAttempts = LOGIN_MAX_ATTEMPTS;
$windowSeconds = LOGIN_WINDOW_SECONDS;
$lockSeconds = LOGIN_LOCK_SECONDS;

function readLoginAttempt(database $db, string $username, string $ip, int $now, bool $forUpdate = false): ?array
{
    $lockSql = $forUpdate ? ' FOR UPDATE' : '';
    $attemptQuery = $db->query(
        "SELECT attempt_count, first_attempt_at, locked_until
         FROM login_attempts
         WHERE username = :username
           AND ip_address = :ip_address{$lockSql}",
        [':username' => $username, ':ip_address' => $ip]
    );
    if (($attemptQuery['status'] ?? '') !== 'success' || empty($attemptQuery['data'])) {
        return null;
    }
    $row = $attemptQuery['data'][0];
    return [
        'count' => (int)($row->attempt_count ?? 0),
        'first_at' => !empty($row->first_attempt_at) ? (strtotime((string)$row->first_attempt_at) ?: $now) : $now,
        'locked_until' => !empty($row->locked_until) ? (strtotime((string)$row->locked_until) ?: 0) : 0,
    ];
}

function persistLoginAttempt(database $db, string $username, string $ip, array $attempt): void
{
    $db->query(
        "INSERT INTO login_attempts (username, ip_address, attempt_count, first_attempt_at, locked_until)
         VALUES (:username, :ip_address, :attempt_count, :first_attempt_at, :locked_until)
         ON DUPLICATE KEY UPDATE
            attempt_count = attempt_count + 1,
            first_attempt_at = COALESCE(first_attempt_at, VALUES(first_attempt_at)),
            locked_until = VALUES(locked_until)",
        [
            ':username' => $username,
            ':ip_address' => $ip,
            ':attempt_count' => (int)$attempt['count'],
            ':first_attempt_at' => date('Y-m-d H:i:s', (int)$attempt['first_at']),
            ':locked_until' => ((int)$attempt['locked_until'] > 0) ? date('Y-m-d H:i:s', (int)$attempt['locked_until']) : null,
        ]
    );
}

function clearLoginAttempt(database $db, string $username, string $ip): void
{
    $db->query(
        "DELETE FROM login_attempts
         WHERE username = :username
           AND ip_address = :ip_address",
        [':username' => $username, ':ip_address' => $ip]
    );
}

$db = getRequestDatabase();

// Per-IP rate limit check (password spray mitigation)
$ipAttemptKey = '__ip_lock__';
$ipAttempt = ['count' => 0, 'first_at' => $now, 'locked_until' => 0];
$maxIpAttempts = 15;

try {
    $ipAttemptFromDb = readLoginAttempt($db, $ipAttemptKey, $ip, $now);
    if ($ipAttemptFromDb !== null) {
        $ipAttempt = $ipAttemptFromDb;
        if (!empty($ipAttempt['locked_until']) && $ipAttempt['locked_until'] > $now) {
            $_SESSION['error'] = 'Muitas tentativas. Tente novamente em alguns minutos.';
            header('Location: index.php?page=login');
            exit;
        }
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Erro interno. Tente novamente.';
    header('Location: index.php?page=login');
    exit;
}

if (($now - (int)$ipAttempt['first_at']) > $windowSeconds) {
    $ipAttempt = ['count' => 0, 'first_at' => $now, 'locked_until' => 0];
}

$attempt = ['count' => 0, 'first_at' => $now, 'locked_until' => 0];

$db->beginTransaction();
try {
    $attemptFromDb = readLoginAttempt($db, (string)$usuario, $ip, $now, true);
    if ($attemptFromDb !== null) {
        $attempt = $attemptFromDb;
        if (!empty($attempt['locked_until']) && $attempt['locked_until'] > $now) {
            $db->commit();
            $_SESSION['error'] = 'Muitas tentativas. Tente novamente em alguns minutos.';
            header('Location: index.php?page=login');
            exit;
        }
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) { $db->rollback(); }
    $_SESSION['error'] = 'Erro interno. Tente novamente.';
    header('Location: index.php?page=login');
    exit;
}

if (($now - (int)$attempt['first_at']) > $windowSeconds) {
    $attempt = ['count' => 0, 'first_at' => $now, 'locked_until' => 0];
}

// Increment per-IP counter on each failed attempt
function incrementIpAttempt(database $db, string $ip, int $now, array &$ipAttempt, int $maxIpAttempts, int $lockSeconds): void
{
    $ipAttempt['count']++;
    if ($ipAttempt['count'] >= $maxIpAttempts) {
        $ipAttempt['locked_until'] = $now + $lockSeconds;
    }
    persistLoginAttempt($db, '__ip_lock__', $ip, $ipAttempt);
}

$sql = "SELECT id, usuario, nome, senha, ativo, nivel_acesso_id, senha_temporaria FROM usuarios WHERE usuario = :usuario";
$params = [':usuario' => $usuario];
$result = $db->query($sql, $params);

if($result['status'] === 'error') {
    header('Location: index.php?page=404');
    exit;
}
if(!isset($result['data']) || count($result['data']) === 0) {
    incrementIpAttempt($db, $ip, $now, $ipAttempt, $maxIpAttempts, $lockSeconds);
    $attempt['count']++;
    if ($attempt['count'] >= $maxAttempts) {
        $attempt['locked_until'] = $now + $lockSeconds;
    }
    persistLoginAttempt($db, (string)$usuario, $ip, $attempt);
    verifyPassword($senha, '$2y$10$00000000000000000000000000000000000000000000000000000', $dummyHash);
    $db->commit();
    $_SESSION['error'] = 'Usuario ou senha invalidos';
    header('Location: index.php?page=login');
    exit;
}
$senhaHash = $result['data'][0]->senha;
$newHash = null;
if(!verifyPassword($senha, $senhaHash, $newHash)) {
    incrementIpAttempt($db, $ip, $now, $ipAttempt, $maxIpAttempts, $lockSeconds);
    $attempt['count']++;
    if ($attempt['count'] >= $maxAttempts) {
        $attempt['locked_until'] = $now + $lockSeconds;
    }
    persistLoginAttempt($db, (string)$usuario, $ip, $attempt);
    $db->commit();
    $_SESSION['error'] = 'Usuario ou senha invalidos';
    header('Location: index.php?page=login');
    exit;
}
if ($newHash) {
    $db->query("UPDATE usuarios SET senha = :senha WHERE id = :id", [
        ':senha' => $newHash,
        ':id' => $result['data'][0]->id,
    ]);
}
$usuarioDb = $result['data'][0];

if ($db->inTransaction()) { $db->commit(); }

if ((int)($usuarioDb->ativo ?? 0) !== 1) {
    $_SESSION['error'] = 'Credenciais invalidas ou conta desativada.';
    header('Location: index.php?page=login');
    exit;
}

clearLoginAttempt($db, (string)$usuario, $ip);

$nivelAcessoQuery = $db->query(
    "SELECT nome FROM niveis_acesso WHERE id = :id",
    [':id' => $usuarioDb->nivel_acesso_id]
);
if ($nivelAcessoQuery['status'] === 'success' && !empty($nivelAcessoQuery['data'])) {
    $usuarioDb->nivel_acesso = $nivelAcessoQuery['data'][0]->nome;
} else {
    $usuarioDb->nivel_acesso = 'user';
}

session_regenerate_id(true);
$_SESSION['ultimo_acesso'] = time();
$_SESSION['session_started_at'] = $now;

unset($usuarioDb->senha);
$_SESSION['usuario'] = $usuarioDb;

try {
    $sessionToken = createSessionToken();
    $_SESSION['session_token'] = $sessionToken;
    registerUniqueSession($db, (int)$usuarioDb->id, $sessionToken);
} catch (Throwable $e) {
    error_log('Erro ao registrar sessao unica no login: ' . $e->getMessage());
    unset($_SESSION['usuario'], $_SESSION['session_token']);
    $_SESSION['error'] = 'Nao foi possivel iniciar a sessao. Tente novamente.';
    header('Location: index.php?page=login');
    exit;
}

if ((int)($_SESSION['usuario']->senha_temporaria ?? 0) === 1) {
    header('Location: index.php?page=trocar_senha');
    exit;
}

header('Location: index.php?page=home');
exit;
