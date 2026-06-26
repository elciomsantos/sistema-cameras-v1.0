<?php
require_once __DIR__ . '/../inc/navbar.php';
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$db = db();
$locaisService = new LocaisService($db);
$pageNum = max(1, (int)($_GET['page_num'] ?? 1));
$perPage = 50;
$search = trim($_GET['busca'] ?? '');

$total = $locaisService->countByTipoNome('PREDIO PUBLICO', $search);
$totalPages = max(1, ceil($total / $perPage));
$result = $locaisService->getByTipoNome('PREDIO PUBLICO', $search, $pageNum, $perPage);
$locais = $result;
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-building me-2"></i>Lista de Próprios Públicos</h4>
                <a href="?page=administracao" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="page" value="listar_proprios">
                <div class="col-md-4">
                    <input type="text" name="busca" class="form-control form-control-sm"
                        placeholder="Buscar por nome, logradouro, bairro..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>Buscar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?page=listar_proprios" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-times me-1"></i>Limpar
                    </a>
                </div>
            </form>

            <p class="text-muted small">Total: <strong><?= $total ?></strong> próprios públicos encontrados</p>

            <?php if (!empty($locais)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Logradouro</th>
                            <th>Bairro</th>
                            <th>Cidade</th>
                            <th>Secretaria</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locais as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l->nome ?? '') ?></td>
                            <td><?= htmlspecialchars(($l->logradouro ?? '') . ($l->numero ? ', ' . $l->numero : '')) ?></td>
                            <td><?= htmlspecialchars($l->bairro ?? '') ?></td>
                            <td><?= htmlspecialchars($l->cidade ?? '') ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($l->sigla ?? $l->secretaria ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_proprios&page_num=<?= $pageNum - 1 ?>&busca=<?= urlencode($search) ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>">
                        <a class="page-link" href="?page=listar_proprios&page_num=<?= $i ?>&busca=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_proprios&page_num=<?= $pageNum + 1 ?>&busca=<?= urlencode($search) ?>">Próximo</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-building fa-2x mb-3"></i><br>
                Nenhum próprio público encontrado.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
