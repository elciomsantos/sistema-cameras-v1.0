<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('user');

$db = db();
$sessionToken = $_SESSION['session_token'] ?? null;
$usuarioId = currentUserId();
if ($usuarioId && $sessionToken) {
    try {
        $dbCheck = db();
        if (!isUniqueSessionValid($dbCheck, $usuarioId, $sessionToken)) {
            clearLocalSessionState();
            header('Location: index.php?page=login&concurrent=1');
            exit;
        }
    } catch (Throwable $e) {
        error_log('Erro ao validar sessao unica em alterar_senha: ' . $e->getMessage());
    }
}

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

$erro = $_SESSION['erro_alterar_senha'] ?? null;
unset($_SESSION['erro_alterar_senha']);
$sucesso = $_SESSION['sucesso_alterar_senha'] ?? null;
unset($_SESSION['sucesso_alterar_senha']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!validateCsrfToken($csrfToken)) {
        $erro = 'Falha de validacao CSRF.';
    } else {
        $senha_atual = $_POST['senha_atual'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';
        $confirma_senha = $_POST['confirma_senha'] ?? '';

        if (empty($senha_atual) || empty($nova_senha) || empty($confirma_senha)) {
            $erro = 'Todos os campos sao obrigatorios!';
        } elseif ($nova_senha !== $confirma_senha) {
            $erro = 'As novas senhas nao coincidem!';
        } else {
            $passwordErrors = [];
            if (!validatePasswordPolicy($nova_senha, $passwordErrors)) {
                $erro = implode(' ', $passwordErrors);
            } else {
                $userResult = $db->query("SELECT senha FROM usuarios WHERE id = :id", [':id' => $_SESSION['usuario']->id]);
                $storedHash = '';
                if ($userResult['status'] === 'success' && !empty($userResult['data'])) {
                    $storedHash = $userResult['data'][0]->senha ?? '';
                }
                if (!verifyPassword($senha_atual, $storedHash)) {
                    $erro = 'Senha atual incorreta!';
                } else {
                    $senha_hash = hashPassword($nova_senha);
                    $sql = "UPDATE usuarios SET senha = :senha WHERE id = :id";
                    $params = [
                        ':senha' => $senha_hash,
                        ':id' => $_SESSION['usuario']->id
                    ];
                    $result = $db->query($sql, $params);
                    if ($result['status'] === 'success') {
                        $_SESSION['sucesso_alterar_senha'] = 'Senha alterada com sucesso!';
                        header('Location: ?page=alterar_senha');
                        exit;
                    } else {
                        $erro = 'Erro ao alterar a senha!';
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../inc/navbar.php';
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-key me-2"></i>Alterar Senha</h4>
                </div>
                <div class="card-body">

                    <?php if ($sucesso): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <a href="?page=home" class="btn btn-primary">
                            <i class="fas fa-home me-1"></i>Voltar ao inicio
                        </a>
                    <?php else: ?>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="senha_atual" class="form-label">Senha Atual</label>
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                        </div>
                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha"
                                minlength="8" required>
                            <div class="form-text">Minimo de 8 caracteres, com letras maiusculas, minusculas, numeros e caracteres especiais</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirma_senha" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirma_senha" name="confirma_senha"
                                minlength="8" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Salvar Nova Senha
                            </button>
                            <a href="?page=home" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
