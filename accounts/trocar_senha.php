<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('user');

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
        error_log('Erro ao validar sessao unica em trocar_senha: ' . $e->getMessage());
    }
}

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}
if ((int)$_SESSION['usuario']->senha_temporaria !== 1) {
    header('Location: index.php?page=home');
    exit;
}

$erro = $_SESSION['erro_troca_senha'] ?? null;
unset($_SESSION['erro_troca_senha']);

?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Troca de Senha Obrigatória</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Por segurança, você deve trocar sua senha temporária por uma senha permanente de sua escolha.
                    </div>
                    
                    <?php if ($erro): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="senha_atual" class="form-label">Senha Atual (Temporária)</label>
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                        </div>
                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                                    minlength="8" required>
                                <div class="form-text">Mínimo de 8 caracteres, com letras maiúsculas, minúsculas, números e caracteres especiais</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirma_senha" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" 
                                minlength="8" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="submit" class="btn btn-primary">
                                <i class="fas fa-key me-1"></i>Trocar Senha
                            </button>
                            <a href="?page=home" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

