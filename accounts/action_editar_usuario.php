<?php
// Action handler: processa edicao de usuario e redireciona

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: index.php?page=listarUsuario');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['erro_editar_usuario'] = 'Falha de validacao CSRF.';
    header('Location: ?page=listarUsuario');
    exit;
}

$id_usuario = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id_usuario) {
    $_SESSION['erro_editar_usuario'] = 'Usuario invalido.';
    header('Location: ?page=listarUsuario');
    exit;
}

$db = db();

$sql = "SELECT u.id, u.usuario, u.nome, n.nome as nivel_acesso, u.ativo, u.senha_temporaria FROM usuarios u LEFT JOIN niveis_acesso n ON u.nivel_acesso_id = n.id WHERE u.id = :id";
$result = $db->query($sql, [':id' => $id_usuario]);

if ($result['status'] !== 'success' || count($result['data']) === 0) {
    $_SESSION['erro_editar_usuario'] = 'Usuario nao encontrado.';
    header('Location: ?page=listarUsuario');
    exit;
}

$usuario = $result['data'][0];
$beforeAudit = [
    'nome' => $usuario->nome ?? null,
    'usuario' => $usuario->usuario ?? null,
    'nivel_acesso' => $usuario->nivel_acesso ?? null,
    'ativo' => $usuario->ativo ?? null,
    'senha_temporaria' => $usuario->senha_temporaria ?? null
];

$nome = trim($_POST['nome'] ?? '');
$usuario_novo = trim($_POST['usuario'] ?? '');
$nivel_acesso = $_POST['nivel_acesso'] ?? 'user';
if (!in_array($nivel_acesso, ['admin', 'supervisor', 'user'], true)) {
    $nivel_acesso = 'user';
}

$nivel_acesso_id = 3;
if ($nivel_acesso === 'admin') {
    $nivel_acesso_id = 1;
} elseif ($nivel_acesso === 'supervisor') {
    $nivel_acesso_id = 2;
}

$ativo = isset($_POST['ativo']) ? 1 : 0;
$redefinir_senha = isset($_POST['redefinir_senha']);

if ((int)$id_usuario === (int)$_SESSION['usuario']->id && $ativo === 0) {
    $_SESSION['erro_editar_usuario'] = 'Voce nao pode desativar seu proprio usuario.';
    header('Location: ?page=editarUsuario&id=' . $id_usuario);
    exit;
}

if ($nome === '' || $usuario_novo === '') {
    $_SESSION['erro_editar_usuario'] = 'Nome e usuario sao obrigatorios.';
    header('Location: ?page=editarUsuario&id=' . $id_usuario);
    exit;
}

$sql_verifica = "SELECT id FROM usuarios WHERE usuario = :usuario AND id != :id";
$result_verifica = $db->query($sql_verifica, [':usuario' => $usuario_novo, ':id' => $id_usuario]);

if (($result_verifica['status'] ?? 'error') !== 'success') {
    $_SESSION['erro_editar_usuario'] = 'Erro ao validar usuario.';
    header('Location: ?page=editarUsuario&id=' . $id_usuario);
    exit;
}

if (count($result_verifica['data']) > 0) {
    $_SESSION['erro_editar_usuario'] = 'Este nome de usuario ja esta em uso.';
    header('Location: ?page=editarUsuario&id=' . $id_usuario);
    exit;
}

$senhaTemporariaPlain = null;

if ($redefinir_senha) {
    $senhaTemporariaPlain = generateTemporaryPassword(12);
    $senhaHash = hashPassword($senhaTemporariaPlain);
    $sql_update = "UPDATE usuarios SET nome = :nome, usuario = :usuario, nivel_acesso_id = :nivel_acesso_id, ativo = :ativo, senha = :senha, senha_temporaria = 1 WHERE id = :id";
    $params_update = [
        ':nome' => $nome,
        ':usuario' => $usuario_novo,
        ':nivel_acesso_id' => $nivel_acesso_id,
        ':ativo' => $ativo,
        ':senha' => $senhaHash,
        ':id' => $id_usuario
    ];
} else {
    $sql_update = "UPDATE usuarios SET nome = :nome, usuario = :usuario, nivel_acesso_id = :nivel_acesso_id, ativo = :ativo WHERE id = :id";
    $params_update = [
        ':nome' => $nome,
        ':usuario' => $usuario_novo,
        ':nivel_acesso_id' => $nivel_acesso_id,
        ':ativo' => $ativo,
        ':id' => $id_usuario
    ];
}

enableAuditTriggers($db, 'web');
$result_update = $db->query($sql_update, $params_update);

if (($result_update['status'] ?? 'error') === 'success') {
    $afterAudit = [
        'nome' => $nome,
        'usuario' => $usuario_novo,
        'nivel_acesso' => $nivel_acesso,
        'ativo' => $ativo,
        'senha_temporaria' => $redefinir_senha ? 1 : ($usuario->senha_temporaria ?? 0)
    ];
    try {
        auditEvent($db, 'usuarios', $id_usuario, 'UPDATE', $beforeAudit, $afterAudit, 'web');
    } catch (Throwable $e) {
        error_log('[action_editar_usuario] Erro auditoria: ' . $e->getMessage());
    }

    $senhaExibida = $senhaTemporariaPlain;
    $_SESSION['sucesso_editar_usuario'] = 'Usuario atualizado com sucesso.';
    if ($senhaExibida !== null) {
        $_SESSION['sucesso_editar_usuario'] .= ' Senha temporaria gerada!';
        $_SESSION['senha_temporaria_exibida'] = $senhaExibida;
    }
} else {
    $_SESSION['erro_editar_usuario'] = 'Erro ao atualizar usuario.';
}

header('Location: ?page=editarUsuario&id=' . $id_usuario);
exit;
