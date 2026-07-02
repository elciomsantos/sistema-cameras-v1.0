<?php
// Action handler: processa recuperacao de senha e redireciona

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: index.php?page=recuperar_senha');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['erro_recuperar'] = 'Falha de validacao CSRF.';
    header('Location: ?page=recuperar_senha');
    exit;
}

$rateLimitPassed = false;
if (class_exists('RateLimiter', true)) {
    $rateLimiter = new RateLimiter();
    $rateLimitKey = 'recuperar_senha:' . getSessionClientIp();
    $rateLimitPassed = $rateLimiter->consume($rateLimitKey, 3, 900);
}

if (!$rateLimitPassed) {
    $_SESSION['erro_recuperar'] = 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.';
    header('Location: ?page=recuperar_senha');
    exit;
}

$usuario = trim($_POST['text_usuario'] ?? '');
$confirmacao = trim($_POST['confirma_usuario'] ?? '');

if ($usuario === '') {
    $_SESSION['erro_recuperar'] = 'Informe o nome de usuario.';
    header('Location: ?page=recuperar_senha');
    exit;
}

if ($confirmacao === '') {
    $_SESSION['erro_recuperar'] = 'Confirme digitando o nome do usuario novamente.';
    header('Location: ?page=recuperar_senha');
    exit;
}

if ($usuario !== $confirmacao) {
    $_SESSION['erro_recuperar'] = 'Os nomes de usuario nao conferem. Digite o mesmo nome nos dois campos.';
    header('Location: ?page=recuperar_senha');
    exit;
}

$db = db();
$result = $db->query(
    "SELECT id, nome FROM usuarios WHERE usuario = :usuario AND ativo = 1",
    [':usuario' => $usuario]
);

if ($result['status'] === 'success' && !empty($result['data'])) {
    $userData = $result['data'][0];
    $novaSenha = generateTemporaryPassword();
    $senhaHash = hashPassword($novaSenha);

    enableAuditTriggers($db, 'web');
    $db->query(
        "UPDATE usuarios SET senha = :senha, senha_temporaria = 1 WHERE id = :id",
        [':senha' => $senhaHash, ':id' => $userData->id]
    );

    try {
        auditEvent($db, 'usuarios', $userData->id, 'UPDATE',
            ['senha_temporaria' => 0],
            ['senha_temporaria' => 1],
            'web'
        );
    } catch (Throwable $e) {
        error_log('[action_recuperar_senha] Erro ao registrar auditoria: ' . $e->getMessage());
    }

    $_SESSION['nova_senha_gerada'] = $novaSenha;
    $_SESSION['sucesso_recuperar'] = "Senha temporária gerada para o usuário '{$userData->nome}': {$novaSenha}";
} else {
    $_SESSION['sucesso_recuperar'] = 'Se o usuario informado existir, uma senha temporaria foi gerada.';
}

$logUser = $_SESSION['usuario']->usuario ?? 'unknown';
$logUserId = currentUserId() ?? 'unknown';
error_log("[action_recuperar_senha] Solicitante={$logUser}(id={$logUserId}) alvo={$usuario} IP=" . getSessionClientIp());
header('Location: ?page=recuperar_senha');
exit;
