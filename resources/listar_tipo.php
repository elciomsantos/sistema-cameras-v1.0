<?php
use App\Services\LocaisService;

require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$tiposConfig = [
    'escola' => [
        'nome' => 'ESCOLA',
        'label' => 'Escolas',
        'labelSingular' => 'escola',
        'icon' => 'fa-school',
        'cor' => 'bg-success',
        'plural' => 'escolas encontradas',
        'vazio' => 'Nenhuma escola encontrada.',
    ],
    'cmei' => [
        'nome' => 'CMEI',
        'label' => 'CMEIs',
        'labelSingular' => 'CMEI',
        'icon' => 'fa-child',
        'cor' => 'bg-info',
        'plural' => 'CMEIs encontrados',
        'vazio' => 'Nenhum CMEI encontrado.',
    ],
    'cei' => [
        'nome' => 'CEI',
        'label' => 'CEIs',
        'labelSingular' => 'CEI',
        'icon' => 'fa-baby',
        'cor' => 'bg-primary',
        'plural' => 'CEIs encontrados',
        'vazio' => 'Nenhum CEI encontrado.',
    ],
    'cras' => [
        'nome' => 'CRAS',
        'label' => 'CRAS',
        'labelSingular' => 'CRAS',
        'icon' => 'fa-hand-holding-heart',
        'cor' => 'bg-warning text-dark',
        'plural' => 'CRAS encontrados',
        'vazio' => 'Nenhum CRAS encontrado.',
    ],
    'caps' => [
        'nome' => 'CAPS',
        'label' => 'CAPS',
        'labelSingular' => 'CAPS',
        'icon' => 'fa-brain',
        'cor' => 'bg-secondary',
        'plural' => 'CAPS encontrados',
        'vazio' => 'Nenhum CAPS encontrado.',
    ],
    'proprios' => [
        'nome' => 'PREDIO PUBLICO',
        'label' => 'Próprios Públicos',
        'labelSingular' => 'próprio público',
        'icon' => 'fa-building',
        'cor' => 'bg-dark',
        'plural' => 'próprios encontrados',
        'vazio' => 'Nenhum próprio público encontrado.',
    ],
];

$tipoKey = strtolower(trim((string) filter_input(INPUT_GET, 'tipo')));
if (!isset($tiposConfig[$tipoKey])) {
    $tipoKey = 'escola';
}
$cfg = $tiposConfig[$tipoKey];

$db = db();
$locaisService = new LocaisService($db);
$pageNum = max(1, filter_input(INPUT_GET, 'page_num', FILTER_VALIDATE_INT) ?: 1);
$perPage = 50;
$search = trim((string) filter_input(INPUT_GET, 'busca'));

$total = $locaisService->countByTipoNome($cfg['nome'], $search);
$totalPages = max(1, ceil($total / $perPage));
$locais = $locaisService->getByTipoNome($cfg['nome'], $search, $pageNum, $perPage);
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header <?= $cfg['cor'] ?> text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas <?= $cfg['icon'] ?> me-2"></i>Lista de <?= htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') ?></h4>
                <a href="?page=administracao" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="page" value="listar_tipo">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipoKey, ENT_QUOTES, 'UTF-8') ?>">
                <div class="col-md-4">
                    <input type="text" name="busca" class="form-control form-control-sm"
                        placeholder="Buscar por nome, logradouro, bairro..."
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>Buscar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?page=listar_tipo&tipo=<?= htmlspecialchars($tipoKey, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-times me-1"></i>Limpar
                    </a>
                </div>
            </form>

            <p class="text-muted small">Total: <strong><?= $total ?></strong> <?= htmlspecialchars($cfg['plural'], ENT_QUOTES, 'UTF-8') ?></p>

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
                            <td><?= htmlspecialchars($l->nome ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(($l->logradouro ?? '') . ($l->numero ? ', ' . $l->numero : ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($l->bairro ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($l->cidade ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($l->sigla ?? $l->secretaria ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_tipo&tipo=<?= htmlspecialchars($tipoKey, ENT_QUOTES, 'UTF-8') ?>&page_num=<?= $pageNum - 1 ?>&busca=<?= urlencode($search) ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>">
                        <a class="page-link" href="?page=listar_tipo&tipo=<?= htmlspecialchars($tipoKey, ENT_QUOTES, 'UTF-8') ?>&page_num=<?= $i ?>&busca=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_tipo&tipo=<?= htmlspecialchars($tipoKey, ENT_QUOTES, 'UTF-8') ?>&page_num=<?= $pageNum + 1 ?>&busca=<?= urlencode($search) ?>">Próximo</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas <?= $cfg['icon'] ?> fa-2x mb-3"></i><br>
                <?= htmlspecialchars($cfg['vazio'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>