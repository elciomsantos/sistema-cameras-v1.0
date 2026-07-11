<?php
requererAcesso('supervisor');

$db = db();
$isAdmin = ($_SESSION['usuario']->nivel_acesso ?? '') === 'admin';

$totalUsers = 0;
$totalEscolas = 0;
$totalCmeis = 0;
$totalOperadoras = 0;
$totalProprios = 0;

$cacheKey = 'admin_stats_' . ($isAdmin ? 'full' : 'user');
if (function_exists('apcu_fetch')) {
    $cached = apcu_fetch($cacheKey);
    if ($cached !== false) {
        $totalUsers = $cached['users'] ?? 0;
        $totalEscolas = $cached['escolas'] ?? 0;
        $totalCmeis = $cached['cmeis'] ?? 0;
        $totalProprios = $cached['proprios'] ?? 0;
        $totalOperadoras = $cached['operadoras'] ?? 0;
    }
}

if (empty($cached)) {
    $totalUsers = 0;
    if ($isAdmin) {
        $r = $db->query("SELECT COUNT(*) as total FROM usuarios");
        if ($r['status'] === 'success') $totalUsers = (int)($r['data'][0]->total ?? 0);
    }

    $contagensLocais = ['escolas' => 0, 'cmeis' => 0, 'proprios' => 0];
    $r = $db->query(
        "SELECT tl.nome AS tipo, COUNT(l.id) AS total
         FROM locais l
         INNER JOIN tipos_locais tl ON tl.id = l.tipo_local_id
         WHERE l.deleted_at IS NULL
           AND tl.nome IN ('ESCOLA', 'CMEI', 'PREDIO PUBLICO')
         GROUP BY tl.nome"
    );
    if ($r['status'] === 'success') {
        foreach ($r['data'] as $row) {
            if ($row->tipo === 'ESCOLA')         $contagensLocais['escolas']  = (int)$row->total;
            elseif ($row->tipo === 'CMEI')      $contagensLocais['cmeis']    = (int)$row->total;
            elseif ($row->tipo === 'PREDIO PUBLICO') $contagensLocais['proprios'] = (int)$row->total;
        }
    }
    $totalEscolas  = $contagensLocais['escolas'];
    $totalCmeis    = $contagensLocais['cmeis'];
    $totalProprios = $contagensLocais['proprios'];

    $totalOperadoras = 0;
    $r = $db->query("SELECT COUNT(*) as total FROM origem_link");
    if ($r['status'] === 'success') $totalOperadoras = (int)($r['data'][0]->total ?? 0);

    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, [
            'users' => $totalUsers, 'escolas' => $totalEscolas,
            'cmeis' => $totalCmeis, 'proprios' => $totalProprios,
            'operadoras' => $totalOperadoras,
        ], 300);
    }
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-cog me-2"></i>Administração do Sistema
                    </h3>
                </div>
                <div class="card-body">
                    <!-- <div class="alert alert-info">
                        <h5><i class="fas fa-tools me-2"></i>Ferramentas administrativas</h5>
                        <p class="mb-0">Gerencie usuários, escolas, CMEIs, operadoras de link e próprios públicos cadastrados no sistema.</p>
                    </div> -->

                    <!-- Stats row -->
                    <div class="row mb-4">
                        <?php if ($isAdmin): ?>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-primary text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalUsers ?></h3>
                                    <small>Usuários</small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-success text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalEscolas ?></h3>
                                    <small>Escolas</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-info text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalCmeis ?></h3>
                                    <small>CMEIs</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-warning text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalOperadoras ?></h3>
                                    <small>Operadoras</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-secondary text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalProprios ?></h3>
                                    <small>Próprios</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action cards row -->
                    <div class="row">
                        <?php if ($isAdmin): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    <h5>Usuários</h5>
                                    <p>Gerenciar contas de acesso ao sistema</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listarUsuario" class="btn btn-primary">Listar</a>
                                        <a href="?page=cadastroUsuario" class="btn btn-outline-primary">Novo</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-school fa-3x text-success mb-3"></i>
                                    <h5>Escolas</h5>
                                    <p>Relação de escolas municipais cadastradas</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_escolas" class="btn btn-success">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-child fa-3x text-info mb-3"></i>
                                    <h5>CMEIs</h5>
                                    <p>Centros Municipais de Educação Infantil</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_cmeis" class="btn btn-info text-white">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-network-wired fa-3x text-warning mb-3"></i>
                                    <h5>Links / Operadoras</h5>
                                    <p>Operadoras de link cadastradas</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_operadoras" class="btn btn-warning text-dark">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-secondary">
                                <div class="card-body text-center">
                                    <i class="fas fa-building fa-3x text-secondary mb-3"></i>
                                    <h5>Próprios Públicos</h5>
                                    <p>Prédios públicos municipais</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_proprios" class="btn btn-secondary">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-dark">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-3x text-dark mb-3"></i>
                                    <h5>Auditoria</h5>
                                    <p>Acompanhar alterações no sistema</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=auditoria_cameras" class="btn btn-dark">Auditoria</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-th-list fa-3x text-primary mb-3"></i>
                                    <h5>Tabelas Lookup</h5>
                                    <p>Gerenciar tabelas de referência do sistema</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=admin_tabelas" class="btn btn-primary">Abrir Painel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
