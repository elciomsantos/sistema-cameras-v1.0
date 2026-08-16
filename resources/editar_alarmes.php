<?php
use App\Services\LookupService;

require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');
require_once __DIR__ . '/../config/database.php';

$lookup = new LookupService();
$statusList = array_map(fn($r) => (array)$r, $lookup->getStatusList());
$regiaoList = array_map(fn($r) => (array)$r, $lookup->getRegioesList());
?>

<?php vite_page_css('editar-alarmes'); ?>

<div class="container mt-4 mb-5">
    <div class="editar-alarme-wrapper">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Alarme</h3>
                <p class="text-muted mb-0">Selecione um alarme para editar os dados.</p>
            </div>
            <a href="?page=controle&amp;modulo=alarmes" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Buscar e Editar Alarme</h5>
        </div>
        <div class="card-body p-4">
            <div class="card alarme-section-card mb-3">
                <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                    <i class="fas fa-search"></i> Buscar e Selecionar Alarme
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Nome/Local</label>
                            <input type="text" id="filtroNome" class="form-control" placeholder="Nome do local do alarme...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Conta</label>
                            <input type="number" id="filtroConta" class="form-control" placeholder="Conta">
                        </div>
                        <div class="col-md-2 d-grid align-self-end">
                            <button type="button" class="btn btn-outline-primary" id="btnBuscar"><i class="fas fa-search me-1"></i>Buscar</button>
                        </div>
                        <div class="col-md-4 d-flex justify-content-end align-items-end gap-2">
                            <a href="?page=cadastro_alarmes" class="btn btn-success"><i class="fas fa-plus me-1"></i>Novo alarme</a>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Selecionar alarme</label>
                        <select id="alarmeSelector" class="form-select">
                            <option value="">Carregando alarmes...</option>
                        </select>
                    </div>
                </div>
            </div>

            <?php $modo = 'editar'; $formId = 'formEditarAlarme'; include __DIR__ . '/../inc/partials/alarme-form.php'; ?>
        </div>
    </div>
    </div>
</div>

<?php vite_page_js('editar-alarmes'); ?>
