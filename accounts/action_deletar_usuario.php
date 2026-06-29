<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erro'] = 'Metodo invalido para exclusao de usuario.';
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
    $_SESSION['erro'] = 'Voce nao pode excluir seu proprio usuario!';
    header('Location: index.php?page=listarUsuario');
    exit;
}

enableAuditTriggers($db, 'web');

$sql = "UPDATE usuarios SET deleted_at = NOW(), ativo = 0 WHERE id = :id AND deleted_at IS NULL";
$params = [':id' => $id];
$result = $db->query($sql, $params);

if ($result['status'] === 'success') {
    $_SESSION['sucesso'] = 'Usuario excluido com sucesso!';
} else {
    $_SESSION['erro'] = 'Erro ao excluir usuario!';
}
header('Location: index.php?page=listarUsuario');
exit;
