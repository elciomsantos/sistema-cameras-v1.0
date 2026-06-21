<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']->nivel_acesso !== 'admin') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erro'] = 'Metodo invalido para bloqueio de usuario.';
    header('Location: index.php?page=listarUsuario');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['erro'] = 'Falha de validacao CSRF.';
    header('Location: index.php?page=listarUsuario');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['erro'] = 'ID de usuario invalido.';
    header('Location: index.php?page=listarUsuario');
    exit;
}

$db = db();

if ($id === (int)$_SESSION['usuario']->id) {
    $_SESSION['erro'] = 'Voce nao pode bloquear seu proprio usuario!';
    header('Location: index.php?page=listarUsuario');
    exit;
}

enableAuditTriggers($db, 'web');

$sql = "UPDATE usuarios SET ativo = 0 WHERE id = :id";
$params = [':id' => $id];
$result = $db->query($sql, $params);

if ($result['status'] === 'success') {
    $_SESSION['sucesso'] = 'Usuario bloqueado com sucesso!';
} else {
    $_SESSION['erro'] = 'Erro ao bloquear usuario!';
}
header('Location: index.php?page=listarUsuario');
exit;
