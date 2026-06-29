<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: index.php?page=administracao');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['erro_cadastro'] = 'Falha de validacao CSRF.';
    header('Location: index.php?page=administracao');
    exit;
}

$db = db();

$nome = trim($_POST['text_nome'] ?? '');
$usuario = trim($_POST['text_usuario'] ?? '');
$senha = $_POST['text_senha'] ?? '';
$nivel_acesso = $_POST['text_nivel_acesso'] ?? 'user';
if (!in_array($nivel_acesso, ['admin', 'supervisor', 'user'], true)) {
    $nivel_acesso = 'user';
}

$nivel_acesso_id = 3;
if ($nivel_acesso === 'admin') {
    $nivel_acesso_id = 1;
} elseif ($nivel_acesso === 'supervisor') {
    $nivel_acesso_id = 2;
}

if (empty($nome) || empty($usuario) || empty($senha)) {
    $_SESSION['erro_cadastro'] = 'Todos os campos sao obrigatorios!';
    header('Location: index.php?page=administracao');
    exit;
}

$passwordErrors = [];
if (!validatePasswordPolicy($senha, $passwordErrors)) {
    $_SESSION['erro_cadastro'] = implode(' ', $passwordErrors);
    header('Location: index.php?page=administracao');
    exit;
}

try {
    $sql = "SELECT COUNT(*) AS total FROM usuarios WHERE usuario = :usuario";
    $result = $db->query($sql, [':usuario' => $usuario]);
    if ($result['status'] === 'success' && !empty($result['data']) && $result['data'][0]->total >= 1) {
        $_SESSION['usuario_existe'] = true;
        header('Location: index.php?page=administracao');
        exit;
    }
} catch (Exception $ex) {
    error_log('Erro ao verificar usuario: ' . $ex->getMessage());
}

try {
    $sql = "SELECT COUNT(*) AS total FROM usuarios WHERE nome = :nome";
    $result = $db->query($sql, [':nome' => $nome]);
    if ($result['status'] === 'success' && !empty($result['data']) && $result['data'][0]->total >= 1) {
        $_SESSION['nome_existe'] = true;
        header('Location: index.php?page=administracao');
        exit;
    }
} catch (Exception $ex) {
    error_log('Erro ao verificar nome: ' . $ex->getMessage());
}

$senha_hash = hashPassword($senha);

enableAuditTriggers($db, 'web');

$sql = "INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, senha_temporaria) VALUES (:nome, :usuario, :senha, :nivel_acesso_id, 1)";
$params = [
    ':nome' => $nome,
    ':usuario' => $usuario,
    ':senha' => $senha_hash,
    ':nivel_acesso_id' => $nivel_acesso_id
];

$result = $db->query($sql, $params);

if ($result['status'] === 'success') {
    $_SESSION["status_cadastro"] = true;
} else {
    $_SESSION['erro_cadastro'] = 'Erro ao cadastrar usuario!';
}

header('Location: index.php?page=administracao');
exit;
