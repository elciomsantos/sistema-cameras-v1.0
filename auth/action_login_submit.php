<?php

use App\Exceptions\AuthException;
use App\Services\AuthService;

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

try {
    $auth = new AuthService($usuario, $senha);
    $result = $auth->authenticate();
} catch (AuthException $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: index.php?page=login');
    exit;
} catch (Throwable $e) {
    error_log('Erro no login: ' . $e->getMessage());
    $_SESSION['error'] = 'Erro interno. Tente novamente.';
    header('Location: index.php?page=login');
    exit;
}

if ((int)($_SESSION['usuario']->senha_temporaria ?? 0) === 1) {
    header('Location: index.php?page=trocar_senha');
    exit;
}

header('Location: index.php?page=home');
exit;
