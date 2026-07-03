<?php
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$locaisService = new LocaisService();

// Carrega filtros
$filters = [
    'search' => trim(filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? ''),
    'tipo_local_id' => filter_input(INPUT_GET, 'tipo_local_id', FILTER_VALIDATE_INT) ?? '',
    'camera' => filter_input(INPUT_GET, 'camera', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '',
];
$filters['camera'] = in_array($filters['camera'], ['sim', 'nao'], true) ? $filters['camera'] : '';
$usuarioSecretariaId = getUsuarioSecretariaId();
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
if ($nivel !== 'admin' && $usuarioSecretariaId !== null) {
    $filters['secretaria_id'] = $usuarioSecretariaId;
}
$pageNum = max(1, filter_input(INPUT_GET, 'page_num', FILTER_VALIDATE_INT) ?: 1);
$perPage = 50;

$tiposLocais = $locaisService->getAllTiposLocais();

$total = $locaisService->countSearch($filters);
$totalPages = max(1, ceil($total / $perPage));
$locais = $locaisService->search($filters, $pageNum, $perPage);
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Locais Cadastrados</h4>
            <div>
                <a href="?page=cadastro_locais" class="btn btn-outline-light btn-sm me-1">
                    <i class="fas fa-plus me-1"></i>Novo Local
                </a>
                <a href="?page=administracao" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['sucesso'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['sucesso'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php unset($_SESSION['sucesso']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['erro'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['erro'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php unset($_SESSION['erro']); ?>
                </div>
            <?php endif; ?>

            <form method="get" id="formFiltrosLocais" class="row g-2 mb-3">
                <input type="hidden" name="page" value="listar_locais">
                <div class="col-md-3">
                    <input type="text" name="busca" class="form-control form-control-sm"
                        placeholder="Buscar por nome, logradouro, bairro..."
                        value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <select name="tipo_local_id" class="form-select form-select-sm">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($tiposLocais as $t): ?>
                        <option value="<?= (int)$t->id ?>" <?= $filters['tipo_local_id'] !== '' && (int)$filters['tipo_local_id'] === (int)$t->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t->nome, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="camera" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="sim" <?= $filters['camera'] === 'sim' ? 'selected' : '' ?>>Com camera</option>
                        <option value="nao" <?= $filters['camera'] === 'nao' ? 'selected' : '' ?>>Sem camera</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="?page=listar_locais" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
                <div class="col-md-2">
                    <button type="button" onclick="exportCsv()" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-file-csv me-1"></i>Exportar CSV
                    </button>
                </div>
            </form>
            <script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
            function exportCsv() {
                var f = document.getElementById('formFiltrosLocais');
                var q = new URLSearchParams(new FormData(f)).toString();
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '?page=action_listar_locais';
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'export';
                h.value = 'csv';
                form.appendChild(h);
                var t = document.createElement('input');
                t.type = 'hidden';
                t.name = 'csrf_token';
                t.value = <?= json_encode(getCsrfToken()) ?>;
                form.appendChild(t);
                var d = document.createElement('input');
                d.type = 'hidden';
                d.name = 'filters';
                d.value = q;
                form.appendChild(d);
                document.body.appendChild(form);
                form.submit();
            }
            </script>

            <p class="text-muted small">Total: <strong><?= $total ?></strong> locais encontrados</p>

            <?php if (!empty($locais)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Regiao</th>
                            <th>Endereco</th>
                            <th>Bairro</th>
                            <th>Horario</th>
                            <th>Sec.</th>
                            <th class="text-center">Cameras</th>
                            <th class="text-center">Alarme</th>
                            <th class="text-center">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locais as $l): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($l->nome ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($l->tipo_local_nome ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="badge" style="background-color:#6f42c1;"><?= htmlspecialchars($l->regiao_nome ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars(
                                trim(($l->logradouro ?? '') . ($l->numero ? ', ' . $l->numero : '')),
                                ENT_QUOTES, 'UTF-8'
                            ) ?: '-' ?></td>
                            <td><?= htmlspecialchars($l->bairro ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><small><?= htmlspecialchars($l->horario_funcionamento ?? '-', ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($l->sigla ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="text-center">
                                <?php $totalCam = (int)($l->total_cameras ?? 0); ?>
                                <span class="badge <?= $totalCam > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $totalCam ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($l->tem_alarme ?? 0): ?>
                                    <span class="badge bg-success">Sim</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nao</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="?page=editar_locais&id=<?= (int)$l->id ?>"
                                    class="btn btn-sm btn-outline-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_locais&page_num=<?= $pageNum - 1 ?>&busca=<?= urlencode($filters['search']) ?>&tipo_local_id=<?= urlencode($filters['tipo_local_id']) ?>&camera=<?= urlencode($filters['camera']) ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>">
                        <a class="page-link" href="?page=listar_locais&page_num=<?= $i ?>&busca=<?= urlencode($filters['search']) ?>&tipo_local_id=<?= urlencode($filters['tipo_local_id']) ?>&camera=<?= urlencode($filters['camera']) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_locais&page_num=<?= $pageNum + 1 ?>&busca=<?= urlencode($filters['search']) ?>&tipo_local_id=<?= urlencode($filters['tipo_local_id']) ?>&camera=<?= urlencode($filters['camera']) ?>">Proximo</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-map-marker-alt fa-2x mb-3"></i><br>
                Nenhum local encontrado.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
