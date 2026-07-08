<?php
$modo = $modo ?? 'crud';
$pending = $pending ?? ($modo === 'crud');
$tipo = $tipo ?? 'camera';
?>
<?php if ($modo === 'crud'): ?>
<!-- Anexos (fotos / documentos)<?= $pending ? ' - aparece apos salvar' : '' ?> -->
<div class="<?= $pending ? 'anexos-section-pending ' : '' ?>card alarme-section-card mb-3 <?= $pending ? 'mt-4' : 'mt-3' ?> anexos-editable" id="anexosSection">
    <div class="card-header alarme-section-header d-flex align-items-center gap-2">
        <i class="fas fa-paperclip"></i>
        <span>Anexos</span>
    </div>
    <div class="card-body">
        <div class="anexos-dropzone text-center p-4 border border-dashed rounded mb-3">
            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
            <p class="mb-1">Arraste arquivos aqui ou clique para selecionar</p>
            <small class="text-muted">Imagens, PDF, Word, Excel (max 10MB)</small>
            <input type="file" class="anexos-file-input d-none" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
        </div>
        <div class="anexos-progress-container d-none mb-3">
            <div class="d-flex justify-content-between mb-1">
                <small class="anexos-progress-text">Enviando...</small>
            </div>
            <div class="progress progress-thin">
                <div class="anexos-progress progress-bar progress-bar-start" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        <div class="anexos-list" data-empty-msg="Nenhum anexo cadastrado."></div>
    </div>
</div>
<?php else: ?>
<div id="secaoAnexosManutencao" class="anexos-section-pending d-none mt-4">
    <hr>
    <h6><i class="fas fa-paperclip me-2"></i>Anexos da Manutenção</h6>
    <p class="text-muted small mb-3">Anexe fotos de peças trocadas ou estado atual do equipamento.</p>
    <div class="anexos-editable">
        <div class="anexos-dropzone text-center p-4 border border-dashed rounded">
            <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block text-primary"></i>
            <p class="mb-1">Arraste arquivos aqui ou clique para selecionar</p>
            <small class="text-muted">Imagens, PDF, DOC, XLS — Máx. 10MB cada</small>
            <input type="file" class="anexos-file-input d-none" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
        </div>
        <div class="anexos-progress-container d-none mt-2">
            <div class="progress progress-thin">
                <div class="progress-bar progress-bar-striped progress-bar-animated progress-bar-start" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="anexos-progress-text text-muted mt-1 d-block"></small>
        </div>
        <div class="anexos-error-msg text-danger small mt-1"></div>
        <div class="anexos-list mt-2" data-manutencao-<?= $tipo ?>-id="" data-empty-msg="Nenhum anexo cadastrado para esta manutenção.">
            <p class="text-muted text-center mb-0">Nenhum anexo cadastrado.</p>
        </div>
    </div>
</div>
<?php endif; ?>
