<?php
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$db = db();
$notificacaoService = new NotificacaoService();

$usuarioSecretariaId = getUsuarioSecretariaId();
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
$secretariaId = ($nivel === 'user' && $usuarioSecretariaId !== null) ? $usuarioSecretariaId : null;

$notificacaoService->gerarPendencias($secretariaId);

$page = max(1, (int)($_GET['page_num'] ?? 1));
$filtroTipo = trim((string)($_GET['filtro_tipo'] ?? ''));

$result = $notificacaoService->getAll($page, 30, $filtroTipo, $secretariaId);
$notificacoes = $result['data'];
$total = $result['total'];
$perPage = $result['per_page'];

$totalPages = max(1, (int)ceil($total / $perPage));

$tiposNotificacao = [
    'manutencao_atrasada' => 'Manutenção Atrasada',
];

$sucesso = $_SESSION['sucesso'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['sucesso'], $_SESSION['erro']);

vite_page_js('notificacoes');
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-bell me-2"></i>Notificações
        </h1>
        <div>
            <?php if ($total > 0): ?>
            <form method="post" action="?page=action_notificacoes" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="acao" value="marcar_todas_lidas">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-check-double me-1"></i>Marcar todas como lidas
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($sucesso): ?>
    <div class="alert alert-success"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-1"></i>Total: <strong><?= $total ?></strong> notificação(ões)</span>
            <div class="d-flex gap-2">
                <form method="get" class="d-flex gap-2">
                    <input type="hidden" name="page" value="notificacoes">
                    <select name="filtro_tipo" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($tiposNotificacao as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $filtroTipo === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($filtroTipo !== ''): ?>
                    <a href="?page=notificacoes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($notificacoes)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">Nenhuma notificação pendente.</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notificacoes as $n): ?>
                <div class="list-group-item <?= !$n->lida ? 'list-group-item-warning' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if (!$n->lida): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation"></i></span>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($n->titulo, ENT_QUOTES, 'UTF-8') ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($tiposNotificacao[$n->tipo] ?? $n->tipo, ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <?php if ($n->mensagem): ?>
                            <p class="mb-1 text-muted small"><?= htmlspecialchars($n->mensagem, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($n->created_at)) ?>
                            </small>
                        </div>
                        <div class="d-flex gap-1 ms-3 flex-shrink-0">
                            <?php if ($n->link): ?>
                            <a href="<?= htmlspecialchars($n->link, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!$n->lida): ?>
<form method="post" action="?page=action_notificacoes" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="acao" value="marcar_lida">
                                <input type="hidden" name="id" value="<?= (int)$n->id ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar como lida">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=notificacoes&page_num=<?= $page - 1 ?>&filtro_tipo=<?= urlencode($filtroTipo) ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=notificacoes&page_num=<?= $i ?>&filtro_tipo=<?= urlencode($filtroTipo) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=notificacoes&page_num=<?= $page + 1 ?>&filtro_tipo=<?= urlencode($filtroTipo) ?>">Próximo</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
