<?php
$sectionPrefix ??= 'Especifica';
$ckPrefix ??= '';
?>

<div id="secao<?= $sectionPrefix ?>LPR" class="hidden-init">
    <hr>
    <h6 class="text-primary mb-3"><i class="fas fa-car me-1"></i>Especifica&ccedil;&otilde;es LPR</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Sentido da Via</label>
            <input type="text" name="lpr_sentido_via" class="form-control"
                value="<?= htmlspecialchars($formData['lpr_sentido_via'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                maxlength="50" placeholder="Ex: CRESCENTE, DECRESCENTE">
        </div>
        <div class="col-md-4">
            <label class="form-label">Faixa Monitorada</label>
            <input type="text" name="lpr_faixa_monitorada" class="form-control"
                value="<?= htmlspecialchars($formData['lpr_faixa_monitorada'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                maxlength="50" placeholder="Ex: FAIXA 1, FAIXA 2">
        </div>
        <div class="col-md-4">
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="lpr_leitura_noturna" id="<?= $ckPrefix ?>lprLeituraNoturna" value="1"
                    <?= !empty($formData['lpr_leitura_noturna']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $ckPrefix ?>lprLeituraNoturna">Leitura Noturna</label>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">URL de Acesso</label>
            <input type="url" name="lpr_url_acesso" class="form-control"
                value="<?= htmlspecialchars($formData['lpr_url_acesso'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                maxlength="2083" placeholder="https://...">
        </div>
    </div>
</div>

<div id="secao<?= $sectionPrefix ?>DVR" class="hidden-init">
    <hr>
    <h6 class="text-primary mb-3"><i class="fas fa-hdd me-1"></i>Especifica&ccedil;&otilde;es DVR</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Modelo</label>
            <input type="text" name="dvr_modelo" class="form-control" required
                value="<?= htmlspecialchars($formData['dvr_modelo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                maxlength="80" placeholder="Ex: DVR 1104, DS-7104">
            <div class="invalid-feedback">Informe o modelo do DVR</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Canais</label>
            <input type="number" name="dvr_canais" class="form-control"
                value="<?= htmlspecialchars($formData['dvr_canais'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                min="1" placeholder="Ex: 4, 8, 16">
        </div>
        <div class="col-md-4">
            <label class="form-label">Armazenamento (TB)</label>
            <input type="number" name="dvr_armazenamento_tb" class="form-control"
                value="<?= htmlspecialchars($formData['dvr_armazenamento_tb'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                min="0" step="0.01" placeholder="Ex: 2.00">
        </div>
    </div>
</div>

<div id="secao<?= $sectionPrefix ?>Totem" class="hidden-init">
    <hr>
    <h6 class="text-primary mb-3"><i class="fas fa-desktop me-1"></i>Especifica&ccedil;&otilde;es Totem</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Quantidade de C&acirc;meras</label>
            <input type="number" name="totem_quantidade_cameras" class="form-control" required
                value="<?= htmlspecialchars($formData['totem_quantidade_cameras'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                min="1" placeholder="Ex: 4">
            <div class="invalid-feedback">Informe a quantidade de c&acirc;meras</div>
        </div>
        <div class="col-md-4">
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="totem_tem_facial" id="<?= $ckPrefix ?>totemTemFacial" value="1"
                    <?= !empty($formData['totem_tem_facial']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $ckPrefix ?>totemTemFacial">
                    <i class="fas fa-face-smile me-1"></i>Reconhecimento Facial
                </label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="totem_tem_lpr" id="<?= $ckPrefix ?>totemTemLpr" value="1"
                    <?= !empty($formData['totem_tem_lpr']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $ckPrefix ?>totemTemLpr">
                    <i class="fas fa-car me-1"></i>Leitor de Placa (LPR)
                </label>
            </div>
        </div>
    </div>
</div>
