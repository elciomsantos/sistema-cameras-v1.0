<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('admin');
$db = db();

$erro = $_SESSION['erro_cadastro'] ?? null;
unset($_SESSION['erro_cadastro']);
$sucesso = $_SESSION['status_cadastro'] ?? null;
unset($_SESSION['status_cadastro']);

$listUsuarios = [];
try {
    $sql = "SELECT u.id, u.nome, u.usuario, n.nome as nivel_acesso, u.created_at AS data, u.senha_temporaria FROM usuarios u LEFT JOIN niveis_acesso n ON u.nivel_acesso_id = n.id ORDER BY u.id DESC";
    $result = $db->query($sql);
    if ($result['status'] === 'success') {
        $listUsuarios = $result['data'];
    }
} catch (Exception $e) {
    error_log('Erro ao buscar usuarios: ' . $e->getMessage());
}
?>

<div class="container mt-4 mb-5">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-1"><i class="fas fa-users me-2"></i>Usu&aacute;rios Cadastrados</h3>
                <p class="text-muted mb-0">Gerencie os usu&aacute;rios do sistema.</p>
            </div>
            <a href="?page=administracao" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if ($erro): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>Usuario cadastrado com sucesso!
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Usu&aacute;rio</th>
                            <th>N&iacute;vel de Acesso</th>
                            <th>Data de Cadastro</th>
                            <th>Status Senha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listUsuarios)): ?>
                            <?php foreach ($listUsuarios as $usuario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usuario->id ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($usuario->nome ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($usuario->usuario ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php
                                                switch($usuario->nivel_acesso) {
                                                    case 'admin':
                                                        echo 'bg-danger';
                                                        break;
                                                    case 'supervisor':
                                                        echo 'bg-info';
                                                        break;
                                                    default:
                                                        echo 'bg-primary';
                                                }
                                            ?>">
                                            <?= htmlspecialchars($usuario->nivel_acesso ?? 'user', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($usuario->data ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($usuario->senha_temporaria == 1): ?>
                                            <span class="badge bg-warning">Senha Tempor&aacute;ria</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Senha Definida</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Nenhum usu&aacute;rio cadastrado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
