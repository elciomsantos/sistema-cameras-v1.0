<?php
// Action handler: processa alteracao de senha e redireciona

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: index.php?page=alterar_senha');
    exit;
}

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['erro_alterar_senha'] = 'Falha de validacao CSRF.';
    header('Location: ?page=alterar_senha');
    exit;
}

$now = time();
$rateKey = 'rate_limit_alterar_senha';
if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 1, 'start' => $now];
} elseif ($now - $_SESSION[$rateKey]['start'] > 900) {
    $_SESSION[$rateKey] = ['count' => 1, 'start' => $now];
} else {
    $_SESSION[$rateKey]['count']++;
    if ($_SESSION[$rateKey]['count'] > 5) {
        $_SESSION['erro_alterar_senha'] = 'Muitas tentativas de alteracao. Aguarde 15 minutos.';
        header('Location: ?page=alterar_senha');
        exit;
    }
}

$senha_atual = $_POST['senha_atual'] ?? '';
$nova_senha = $_POST['nova_senha'] ?? '';
$confirma_senha = $_POST['confirma_senha'] ?? '';

if (empty($senha_atual) || empty($nova_senha) || empty($confirma_senha)) {
    $_SESSION['erro_alterar_senha'] = 'Todos os campos sao obrigatorios!';
    header('Location: ?page=alterar_senha');
    exit;
}

if ($nova_senha !== $confirma_senha) {
    $_SESSION['erro_alterar_senha'] = 'As novas senhas nao coincidem!';
    header('Location: ?page=alterar_senha');
    exit;
}

$passwordErrors = [];
if (!validatePasswordPolicy($nova_senha, $passwordErrors)) {
    $_SESSION['erro_alterar_senha'] = implode(' ', $passwordErrors);
    header('Location: ?page=alterar_senha');
    exit;
}

$db = db();
$userResult = $db->query("SELECT senha FROM usuarios WHERE id = :id", [':id' => $_SESSION['usuario']->id]);
$storedHash = '';
if ($userResult['status'] === 'success' && !empty($userResult['data'])) {
    $storedHash = $userResult['data'][0]->senha ?? '';
}

if (!verifyPassword($senha_atual, $storedHash)) {
    $_SESSION['erro_alterar_senha'] = 'Senha atual incorreta!';
    header('Location: ?page=alterar_senha');
    exit;
}

$senha_hash = hashPassword($nova_senha);
$result = $db->query(
    "UPDATE usuarios SET senha = :senha WHERE id = :id",
    [':senha' => $senha_hash, ':id' => $_SESSION['usuario']->id]
);

if ($result['status'] === 'success') {
    $_SESSION['sucesso_alterar_senha'] = 'Senha alterada com sucesso!';
} else {
    $_SESSION['erro_alterar_senha'] = 'Erro ao alterar a senha!';
}

header('Location: ?page=alterar_senha');
exit;
