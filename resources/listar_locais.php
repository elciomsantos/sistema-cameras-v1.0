<?php
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$locaisService = new LocaisService();

// Carrega filtros
$filters = [
    'nome' => trim(filter_input(INPUT_GET, 'nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? ''),
    'regiao_id' => filter_input(INPUT_GET, 'regiao_id', FILTER_VALIDATE_INT) ?? '',
    'tipo_local_id' => filter_input(INPUT_GET, 'tipo_local_id', FILTER_VALIDATE_INT) ?? '',
    'tem_alarme' => filter_input(INPUT_GET, 'tem_alarme', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '',
    'camera' => filter_input(INPUT_GET, 'camera', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '',
];
$filters['tem_alarme'] = in_array($filters['tem_alarme'], ['sim', 'nao'], true) ? $filters['tem_alarme'] : '';
$filters['camera'] = in_array($filters['camera'], ['sim', 'nao'], true) ? $filters['camera'] : '';
$usuarioSecretariaId = getUsuarioSecretariaId();
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
if ($nivel !== 'admin' && $usuarioSecretariaId !== null) {
    $filters['secretaria_id'] = $usuarioSecretariaId;
}
$pageNum = max(1, filter_input(INPUT_GET, 'page_num', FILTER_VALIDATE_INT) ?: 1);
$perPage = 50;

$tiposLocais = $locaisService->getAllTiposLocais();
$regioes = $locaisService->getAllRegioes();

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

            <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-tag me-2"></i>Busca Avancada</h5>

            <form method="get" id="formFiltrosLocais" class="row g-2 mb-1 align-items-end">
                <input type="hidden" name="page" value="listar_locais">
                <div class="col-md-4">
                    <label for="filtro_nome" class="form-label small mb-1">Nome</label>
                    <div class="position-relative">
                        <input type="text" id="filtro_nome" name="nome" class="form-control form-control-sm"
                            placeholder="Digite o nome"
                            value="<?= htmlspecialchars($filters['nome'], ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="filtro_regiao" class="form-label small mb-1">Regiao</label>
                    <select id="filtro_regiao" name="regiao_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($regioes as $r): ?>
                        <option value="<?= (int)$r->id ?>" <?= $filters['regiao_id'] !== '' && (int)$filters['regiao_id'] === (int)$r->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r->nome, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtro_tipo" class="form-label small mb-1">Tipo</label>
                    <select id="filtro_tipo" name="tipo_local_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($tiposLocais as $t): ?>
                        <option value="<?= (int)$t->id ?>" <?= $filters['tipo_local_id'] !== '' && (int)$filters['tipo_local_id'] === (int)$t->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t->nome, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Filtrar">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="?page=listar_locais" class="btn btn-outline-secondary btn-sm flex-fill" title="Limpar filtros">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
            <form method="get" class="row g-2 mb-4 align-items-end" id="formFiltrosExtrasLocais">
                <input type="hidden" name="page" value="listar_locais">
                <div class="col-md-4">
                    <label for="filtro_alarme" class="form-label small mb-1">Alarme</label>
                    <select id="filtro_alarme" name="tem_alarme" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="sim" <?= $filters['tem_alarme'] === 'sim' ? 'selected' : '' ?>>Com alarme</option>
                        <option value="nao" <?= $filters['tem_alarme'] === 'nao' ? 'selected' : '' ?>>Sem alarme</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filtro_camera" class="form-label small mb-1">Camera</label>
                    <select id="filtro_camera" name="camera" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="sim" <?= $filters['camera'] === 'sim' ? 'selected' : '' ?>>Com camera</option>
                        <option value="nao" <?= $filters['camera'] === 'nao' ? 'selected' : '' ?>>Sem camera</option>
                    </select>
                </div>
            </form>

            <div class="row g-2 mb-3">
                <div class="col-auto">
                    <button type="button" onclick="exportCsv()" class="btn btn-success btn-sm">
                        <i class="fas fa-file-csv me-1"></i>Exportar CSV
                    </button>
                </div>
            </div>
        <script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
            function exportCsv() {
                var fd = new FormData(document.getElementById('formFiltrosLocais'));
                var fd2 = new FormData(document.getElementById('formFiltrosExtrasLocais'));
                for (var pair of fd2.entries()) { fd.append(pair[0], pair[1]); }
                var q = new URLSearchParams(fd).toString();
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

<style nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
            .autocomplete-dropdown {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 1060;
                max-height: 250px;
                overflow-y: auto;
                background: #fff;
                border: 1px solid #ced4da;
                border-top: none;
                border-radius: 0 0 4px 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
                min-width: 100%;
            }

            .autocomplete-dropdown .dropdown-item {
                padding: 8px 12px;
                cursor: pointer;
                font-size: .875rem;
                border-bottom: 1px solid #f0f0f0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .autocomplete-dropdown .dropdown-item:last-child {
                border-bottom: none;            }
            .autocomplete-dropdown .dropdown-item:hover,
            .autocomplete-dropdown .dropdown-item.active {
                background: #e9ecef;            }
        </style>
        <script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
            document.addEventListener('DOMContentLoaded', function() {
                var input = document.getElementById('filtro_nome');
                if (!input) return;

                var apiUrlBase = <?= json_encode(BASE_URL . '/index.php?page=api/api_locais') ?>;
                var dropdown = document.createElement('div');
                dropdown.className = 'autocomplete-dropdown';
                dropdown.style.display = 'none';
                var wrapper = input.parentNode;
                wrapper.style.position = 'relative';
                wrapper.appendChild(dropdown);

                var debounceTimer = null;

                input.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    var val = this.value.trim();
                    if (val.length < 3) {
                        dropdown.style.display = 'none';
                        return;
                    }
                    debounceTimer = setTimeout(function() {
                        fetch(apiUrlBase + '&q=' + encodeURIComponent(val))
                            .then(function(r) {
                                return r.json();
                            })
                            .then(function(data) {
                                dropdown.innerHTML = '';
                                var items = data.data && data.data.data ? data.data.data : (Array.isArray(data.data) ? data.data : []);
                                if (items.length > 0) {
                                    items.forEach(function(item) {
                                        var el = document.createElement('div');
                                        el.className = 'dropdown-item';
                                        el.textContent = item.nome;
                                        el.dataset.value = item.nome;
                                        el.addEventListener('click', function() {
                                            input.value = this.dataset.value;
                                            dropdown.style.display = 'none';
                                            input.form.submit();
                                        });
                                        dropdown.appendChild(el);
                                    });
                                    dropdown.style.display = 'block';
                                } else {
                                    dropdown.style.display = 'none';
                                }
                            })
                            .catch(function(err) {
                                console.error('Autocomplete error:', err);
                                dropdown.style.display = 'none';
                            });
                    }, 300);
                });

                document.addEventListener('click', function(e) {
                    if (!wrapper.contains(e.target)) {
                        dropdown.style.display = 'none';
                    }
                });

                input.addEventListener('blur', function() {
                    setTimeout(function() {
                        dropdown.style.display = 'none';
                    }, 200);
                });
            });
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
                            <th class="text-center">Camera</th>
                            <th class="text-center">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locais as $l): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($l->nome ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($l->tipo_local_nome ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="badge badge-purple"><?= htmlspecialchars($l->regiao_nome ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars(
                                        trim(($l->logradouro ?? '') . ($l->numero ? ', ' . $l->numero : '')),
                                        ENT_QUOTES,
                                        'UTF-8'
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
                                    <?php if ($l->tem_camera ?? 0): ?>
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

            <?php if ($totalPages > 1):
                $filtersQuery = http_build_query(array_filter([
                    'nome' => $filters['nome'],
                    'regiao_id' => $filters['regiao_id'],
                    'tipo_local_id' => $filters['tipo_local_id'],
                    'tem_alarme' => $filters['tem_alarme'],
                    'camera' => $filters['camera'],
                ]));
            ?>
                <nav>
                    <ul class="pagination pagination-sm justify-content-center">
                        <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=listar_locais&page_num=<?= $pageNum - 1 ?>&<?= $filtersQuery ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>">
                                <a class="page-link" href="?page=listar_locais&page_num=<?= $i ?>&<?= $filtersQuery ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=listar_locais&page_num=<?= $pageNum + 1 ?>&<?= $filtersQuery ?>">Proximo</a>
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