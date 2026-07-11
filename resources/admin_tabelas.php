<?php
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$db = db();
$tabela = $_GET['tabela'] ?? '';

$allTables = [
    'secretarias' => [
        'label' => 'Secretarias',
        'icon' => 'fa-building',
        'cols' => ['ID', 'Nome', 'Sigla', 'Tipo', 'Ativo'],
        'fields' => ['nome' => 'Nome', 'sigla' => 'Sigla'],
        'display' => function ($row) {
            $tipoNomes = tiposSecretariaMap();
            $ativo = $row->ativo ? '<span class="badge bg-success">Sim</span>' : '<span class="badge bg-secondary">Nao</span>';
            $tipo = htmlspecialchars($tipoNomes[$row->tipo_id] ?? '-', ENT_QUOTES, 'UTF-8');
            return [$row->id, htmlspecialchars($row->nome, ENT_QUOTES, 'UTF-8'), htmlspecialchars($row->sigla, ENT_QUOTES, 'UTF-8'), $tipo, $ativo];
        },
    ],
    'regioes' => [
        'label' => 'Regiões',
        'icon' => 'fa-map',
        'cols' => ['ID', 'Nome'],
        'fields' => ['nome' => 'Nome'],
        'display' => function ($row) {
            return [$row->id, htmlspecialchars($row->nome, ENT_QUOTES, 'UTF-8')];
        },
    ],
    'tipos_locais' => [
        'label' => 'Tipos de Local',
        'icon' => 'fa-tag',
        'cols' => ['ID', 'Nome'],
        'fields' => ['nome' => 'Nome'],
        'display' => function ($row) {
            return [$row->id, htmlspecialchars($row->nome, ENT_QUOTES, 'UTF-8')];
        },
    ],
    'tipos_equipamento' => [
        'label' => 'Tipos de Equipamento',
        'icon' => 'fa-cogs',
        'cols' => ['ID', 'Nome'],
        'fields' => ['nome' => 'Nome'],
        'display' => function ($row) {
            return [$row->id, htmlspecialchars($row->nome, ENT_QUOTES, 'UTF-8')];
        },
    ],
    'tipos_secretaria' => [
        'label' => 'Tipos de Secretaria',
        'icon' => 'fa-sitemap',
        'cols' => ['ID', 'Nome'],
        'fields' => ['nome' => 'Nome'],
        'display' => function ($row) {
            return [$row->id, htmlspecialchars($row->nome, ENT_QUOTES, 'UTF-8')];
        },
    ],
    'marcas' => [
        'label' => 'Marcas',
        'icon' => 'fa-industry',
        'cols' => ['ID', 'Nome'],
        'fields' => ['nome' => 'Nome'],
        'display' => function ($row) {
            return [$row->id, htmlspecialchars($row->nome, ENT_QUOTES, 'UTF-8')];
        },
    ],
    'status' => [
        'label' => 'Status',
        'icon' => 'fa-flag',
        'cols' => ['ID', 'Nome'],
        'fields' => ['nome' => 'Nome'],
        'display' => function ($row) {
            return [$row->id, htmlspecialchars($row->nome, ENT_QUOTES, 'UTF-8')];
        },
    ],
];

function tiposSecretariaMap(): array
{
    static $map = null;
    if ($map === null) {
        $result = db()->query("SELECT id, nome FROM tipos_secretaria ORDER BY nome");
        $map = [];
        foreach (($result['status'] === 'success' ? $result['data'] : []) as $row) {
            $map[$row->id] = $row->nome;
        }
    }
    return $map;
}

if ($tabela !== '' && !isset($allTables[$tabela])) {
    $_SESSION['erro'] = 'Tabela invalida.';
    header('Location: index.php?page=admin_tabelas');
    exit;
}
?>
<div class="container mt-4">
    <?php if ($tabela === ''): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Tabelas Lookup</h4>
                <a href="?page=administracao" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Administração
                </a>
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
                <div class="row g-3">
                    <?php foreach ($allTables as $key => $cfg): ?>
                    <div class="col-md-4 col-lg-3">
                        <a href="?page=admin_tabelas&tabela=<?= $key ?>" class="text-decoration-none">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center py-4">
                                    <i class="fas <?= $cfg['icon'] ?> fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title"><?= $cfg['label'] ?></h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else:
        $cfg = $allTables[$tabela];
        $msgSucesso = $_SESSION['sucesso'] ?? '';
        $msgErro = $_SESSION['erro'] ?? '';
        unset($_SESSION['sucesso'], $_SESSION['erro']);
        $result = $db->query("SELECT * FROM {$tabela} ORDER BY nome");
        $rows = $result['status'] === 'success' ? $result['data'] : [];
    ?>
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas <?= $cfg['icon'] ?> me-2"></i><?= $cfg['label'] ?></h4>
                <div class="d-flex gap-2">
                    <a href="?page=admin_tabelas" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-th-list me-1"></i>Painel
                    </a>
                    <a href="?page=administracao" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Administração
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($msgSucesso): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($msgSucesso, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($msgErro): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($msgErro, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="post" action="?page=action_administracao" class="row g-2 mb-4 p-3 bg-light rounded border">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="tabela" value="<?= $tabela ?>">
                    <input type="hidden" name="acao" value="criar">
                    <div class="col-12"><strong><i class="fas fa-plus me-1"></i>Adicionar</strong></div>
                    <?php foreach ($cfg['fields'] as $field => $label): ?>
                    <div class="col-md">
                        <input type="text" name="campos[<?= $field ?>]" class="form-control form-control-sm"
                            placeholder="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $field === 'nome' ? 'required' : '' ?>>
                    </div>
                    <?php endforeach; ?>
                    <div class="col-md-auto d-flex align-items-end gap-1">
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i>Salvar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-striped table-sm">
                        <thead class="table-dark">
                            <tr>
                                <?php foreach ($cfg['cols'] as $col): ?>
                                <th><?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                            <tr><td colspan="<?= count($cfg['cols']) + 1 ?>" class="text-center text-muted py-3">Nenhum registro encontrado.</td></tr>
                            <?php else: foreach ($rows as $row): $vals = $cfg['display']($row); ?>
                            <tr>
                                <?php foreach ($vals as $v): ?>
                                <td><?= $v ?></td>
                                <?php endforeach; ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Editar"
                                        onclick="editar(<?= (int)$row->id ?>, <?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="post" action="?page=action_administracao" class="d-inline"
                                        onsubmit="return confirm('Tem certeza que deseja excluir este registro?')">
                                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                        <input type="hidden" name="tabela" value="<?= $tabela ?>">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int)$row->id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalEditar" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="?page=action_administracao">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                        <input type="hidden" name="tabela" value="<?= $tabela ?>">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="modal-header">
                            <h5 class="modal-title">Editar <?= $cfg['label'] ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?php foreach ($cfg['fields'] as $field => $label): ?>
                            <div class="mb-3">
                                <label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="text" name="campos[<?= $field ?>]" id="edit_<?= $field ?>"
                                    class="form-control" required>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($tabela === 'secretarias'): ?>
                            <div class="mb-3">
                                <label class="form-label">Tipo</label>
                                <select name="campos[tipo_id]" id="edit_tipo_id" class="form-select" required>
                                    <?php foreach (tiposSecretariaMap() as $tid => $tnome): ?>
                                    <option value="<?= $tid ?>"><?= htmlspecialchars($tnome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="campos[ativo]" id="edit_ativo" class="form-check-input" value="1">
                                <label class="form-check-label" for="edit_ativo">Ativo</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
            function editar(id, data) {
                document.getElementById('edit_id').value = id;
                <?php foreach (array_keys($cfg['fields']) as $field): ?>
                var el = document.getElementById('edit_<?= $field ?>');
                if (el) el.value = data.<?= $field ?> || '';
                <?php endforeach; ?>
                <?php if ($tabela === 'secretarias'): ?>
                var tipoEl = document.getElementById('edit_tipo_id');
                if (tipoEl) tipoEl.value = data.tipo_id;
                var ativoEl = document.getElementById('edit_ativo');
                if (ativoEl) ativoEl.checked = data.ativo == 1;
                <?php endif; ?>
                new bootstrap.Modal(document.getElementById('modalEditar')).show();
            }
        </script>
    <?php endif; ?>
</div>
</div>
