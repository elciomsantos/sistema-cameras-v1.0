<?php
$modo ??= 'cadastro';
$formId ??= 'formAlarme';
$isEdit = $modo === 'editar';
?>

<form id="<?= $formId ?>" class="needs-validation" novalidate>
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" id="alarme_id">
    <?php endif; ?>

    <input type="hidden" name="local_id" id="local_id" value="">

    <div class="card alarme-section-card mb-3">
        <div class="card-header alarme-section-header d-flex align-items-center gap-2">
            <i class="fas fa-tag"></i> Identifica&ccedil;&atilde;o e Localiza&ccedil;&atilde;o
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Conta <span class="text-danger">*</span></label>
                    <input type="number" name="conta" class="form-control" min="1" required>
                    <div class="invalid-feedback">Informe a conta.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($statusList as $s): ?>
                            <option value="<?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Selecione o status.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Regiao</label>
                    <select name="regiao" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($regiaoList as $r): ?>
                            <option value="<?= htmlspecialchars($r['nome'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data atualizacao <span class="text-danger">*</span></label>
                    <input type="date" name="data_atualizacao" class="form-control" value="<?= !$isEdit ? date('Y-m-d') : '' ?>" required>
                    <div class="invalid-feedback">Informe a data de atualizacao.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nome/Local <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="text" name="local" id="nome_local" class="form-control" maxlength="255" required
                            placeholder="Digite o nome do local para buscar..." autocomplete="off">
                    </div>
                    <div class="invalid-feedback">Informe o local.</div>
                    <div class="form-text">Busque o local ja cadastrado para preencher o endereco automaticamente.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Endereco</label>
                    <input type="text" name="endereco" id="endereco" class="form-control" maxlength="255"
                        placeholder="Logradouro">
                    <div class="form-text">Preenchido automaticamente ao selecionar o local.</div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Numero</label>
                    <input type="text" name="numero" id="numero" class="form-control" maxlength="20">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control" maxlength="100">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control" maxlength="100">
                </div>
                <div class="col-md-1">
                    <label class="form-label">UF</label>
                    <input type="text" name="uf" id="uf" class="form-control" maxlength="2">
                </div>
                <div class="col-md-2">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" id="cep" class="form-control" maxlength="10">
                </div>
            </div>
        </div>
    </div>

    <div class="card alarme-section-card mb-3">
        <div class="card-header alarme-section-header d-flex align-items-center gap-2">
            <i class="fas fa-wifi"></i> Conex&atilde;o e Integra&ccedil;&atilde;o
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">PGM1</label>
                    <input type="text" name="pgm1" class="form-control" maxlength="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label">PGM2</label>
                    <input type="text" name="pgm2" class="form-control" maxlength="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label">MAC</label>
                    <input type="text" name="mac" class="form-control" maxlength="50">
                </div>
                <div class="col-md-3">
                    <label class="form-label">IP</label>
                    <input type="text" name="ip" class="form-control" maxlength="50">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Integracao</label>
                    <input type="date" name="integracao" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">IP DVR</label>
                    <input type="text" name="ip_dvr" class="form-control" maxlength="50">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cameras DVR</label>
                    <input type="number" name="cameras_dvr" class="form-control" min="0">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="checkbox" name="camera_gm" class="form-check-input" value="1" id="cameraGm">
                        <label class="form-check-label" for="cameraGm">Camera GM</label>
                    </div>
                    <input type="number" name="quant_camera_gm" class="form-control mt-2" min="0" placeholder="Qtd. cameras GM">
                </div>
            </div>
        </div>
    </div>

    <div class="card alarme-section-card mb-3">
        <div class="card-header alarme-section-header d-flex align-items-center gap-2">
            <i class="fas fa-cogs"></i> Configura&ccedil;&atilde;o e Documenta&ccedil;&atilde;o
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Modelo da Central</label>
                    <select name="modelo_alarme_id" class="form-select">
                        <option value="">Selecione um modelo...</option>
                        <?php
                        $modelosList = $lookup->getModelosAlarmes();
                        foreach ($modelosList as $m) {
                            echo '<option value="' . (int)$m->id . '">' . htmlspecialchars($m->nome, ENT_QUOTES, 'UTF-8') . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Qtd. Repetidor</label>
                    <input type="number" name="quant_repetidor" class="form-control" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Qtd. Sensores</label>
                    <input type="number" name="qtde_sensores" class="form-control" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Documentacao</label>
                    <input type="text" name="documentacao" class="form-control" maxlength="50">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monitorada</label>
                    <input type="text" name="monitorada" class="form-control" maxlength="50">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Numero SEI</label>
                    <input type="text" name="numero_sei" class="form-control" maxlength="50">
                </div>
                <div class="col-12">
                    <label class="form-label">Observacao</label>
                    <textarea name="observacao" class="form-control char-counter" rows="3" maxlength="4000" data-target="#obsCounter"></textarea>
                    <small class="text-muted"><span id="obsCounter">0</span>/4000 caracteres</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-<?= $isEdit ? 'primary' : 'danger' ?>" id="btnSubmit">
            <i class="fas fa-save me-1"></i><?= $isEdit ? 'Salvar alteracoes' : 'Salvar' ?>
        </button>
        <?php if (!$isEdit): ?>
        <button type="button" class="btn btn-success d-none" id="btnFinalizar">
            <i class="fas fa-check me-1"></i>Finalizar Cadastro
        </button>
        <?php endif; ?>
        <a href="?page=controle_alarmes" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
$pending = !$isEdit;
include __DIR__ . '/anexos-section.php';
?>
