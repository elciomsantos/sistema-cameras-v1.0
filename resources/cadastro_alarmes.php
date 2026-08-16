<?php
use App\Services\LookupService;

require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');
require_once __DIR__ . '/../config/database.php';

$lookup = new LookupService();
$statusList = array_map(fn($r) => (array)$r, $lookup->getStatusList());
$regiaoList = array_map(fn($r) => (array)$r, $lookup->getRegioesList());
?>

<?php vite_page_css('cadastro-alarmes'); ?>

<div class="container mt-4 mb-5 alarme-cadastro-page">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-0"><i class="fas fa-bell me-2"></i>Cadastro de Alarme</h3>
                <p class="text-muted mb-0">Cadastre um novo alarme no sistema.</p>
            </div>
            <a href="?page=controle&amp;modulo=alarmes" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Informa&ccedil;&otilde;es do Alarme</h5>
                </div>
                <div class="card-body p-4">
                    <?php $modo = 'cadastro'; $formId = 'formCadastroAlarme'; include __DIR__ . '/../inc/partials/alarme-form.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php vite_page_js('cadastro-alarmes'); ?>
