<?php
$tiposLocais = $tiposLocais ?? [];
$regioes = $regioes ?? [];
$secretarias = $secretarias ?? [];
$ufs = $ufs ?? [];
$classificacoes = $classificacoes ?? [];

$modo ??= 'cadastro';
$isEdit = $modo === 'editar';
$data ??= null;
$formData ??= [];
$isScopedUser ??= false;
$usuarioSecretariaId ??= null;
?>
<?php
// Helper: resolve field value from PRG flash data, then DB object, then default
$lf = function (string $key, string $default = '') use ($formData, $data): string {
    if (isset($formData[$key])) {
        $val = $formData[$key];
    } elseif ($data !== null && property_exists($data, $key)) {
        $val = $data->$key ?? $default;
    } else {
        $val = $default;
    }
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
};
$lselected = function (string $key, $value, $default = null) use ($formData, $data): string {
    if (isset($formData[$key])) {
        $cmp = $formData[$key];
    } elseif ($data !== null && property_exists($data, $key)) {
        $cmp = $data->$key;
    } else {
        $cmp = $default;
    }
    return ((string)$cmp === (string)$value) ? 'selected' : '';
};
$lchecked = function (string $key) use ($formData, $data): string {
    if (isset($formData[$key])) {
        return $formData[$key] ? 'checked' : '';
    }
    return ($data !== null && !empty($data->$key)) ? 'checked' : '';
};
?>

<form method="post" class="needs-validation" novalidate autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-tag me-2"></i>Identificacao</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="tipo_local_id" class="form-label">Tipo de Local *</label>
            <select class="form-select form-select-sm" id="tipo_local_id" name="tipo_local_id" required<?= !empty($autofocusTipo) ? ' autofocus' : '' ?>>
                <option value="">Selecione...</option>
                <?php foreach ($tiposLocais as $t) : ?>
                <option value="<?= (int)$t->id ?>" <?= $lselected('tipo_local_id', $t->id) ?>>
                    <?= htmlspecialchars($t->nome, ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="nome" class="form-label">Nome do Local *</label>
            <input type="text" class="form-control form-control-sm" id="nome" name="nome" required
                value="<?= $lf('nome') ?>"
                placeholder="Ex: ESCOLA MUNICIPAL PROF JOÃO">
        </div>
        <div class="col-md-3">
            <label for="regiao_id" class="form-label">Regiao</label>
            <select class="form-select form-select-sm" id="regiao_id" name="regiao_id">
                <option value="">Selecione...</option>
                <?php foreach ($regioes as $r) : ?>
                <option value="<?= (int)$r->id ?>" <?= $lselected('regiao_id', $r->id) ?>>
                    <?= htmlspecialchars($r->nome, ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="secretaria_id" class="form-label">Secretaria</label>
            <select class="form-select form-select-sm" id="secretaria_id" name="secretaria_id" <?= $isScopedUser ? 'disabled' : '' ?>>
                <option value="">Nenhuma</option>
                <?php foreach ($secretarias as $s) : ?>
                <option value="<?= (int)$s->id ?>" <?= $isScopedUser && (int)$s->id === (int)$usuarioSecretariaId ? 'selected' : $lselected('secretaria_id', $s->id) ?>>
                    <?= htmlspecialchars($s->sigla . ' - ' . $s->nome, ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($isScopedUser) : ?>
            <input type="hidden" name="secretaria_id" value="<?= $usuarioSecretariaId ?>">
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <label for="horario_funcionamento" class="form-label">Horario de Funcionamento</label>
            <input type="text" class="form-control form-control-sm" id="horario_funcionamento" name="horario_funcionamento"
                value="<?= $lf('horario_funcionamento') ?>"
                placeholder="Ex: Seg a Sex 08:00-18:00">
        </div>
        <div class="col-md-4">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" class="form-control form-control-sm" id="telefone" name="telefone"
                value="<?= $lf('telefone') ?>"
                placeholder="Ex: (43) 3375-0112">
        </div>
        <div class="col-md-4">
            <label for="ramal" class="form-label">Ramal</label>
            <input type="text" class="form-control form-control-sm" id="ramal" name="ramal"
                value="<?= $lf('ramal') ?>"
                placeholder="Ex: 50112">
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control form-control-sm" id="email" name="email"
                value="<?= $lf('email') ?>"
                placeholder="exemplo@dominio.com">
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="tem_alarme" name="tem_alarme" value="1"
                    <?= $lchecked('tem_alarme') ?>>
                <label class="form-check-label" for="tem_alarme">Local possui alarme</label>
            </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="tem_camera" name="tem_camera" value="1"
                    <?= $lchecked('tem_camera') ?>>
                <label class="form-check-label" for="tem_camera">Local possui camera</label>
            </div>
        </div>
        <div class="col-md-6"></div>
    </div>

    <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-address-card me-2"></i>Endereco</h5>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="cep" class="form-label">CEP</label>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm" id="cep" name="cep" maxlength="9"
                    value="<?= $lf('cep') ?>"
                    placeholder="00000-000">
                <button type="button" class="btn btn-outline-primary" id="btnBuscarCep">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="col-md-2">
            <label for="uf" class="form-label">UF</label>
            <select class="form-select form-select-sm" id="uf" name="uf">
                <option value="">Selecione</option>
                <?php foreach ($ufs as $uf) : ?>
                <option value="<?= $uf ?>" <?= $lselected('uf', $uf) ?>><?= $uf ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="cidade" class="form-label">Cidade</label>
            <input type="text" class="form-control form-control-sm" id="cidade" name="cidade"
                value="<?= $lf('cidade') ?>"
                placeholder="Londrina">
        </div>
        <div class="col-md-5">
            <label for="bairro" class="form-label">Bairro</label>
            <input type="text" class="form-control form-control-sm" id="bairro" name="bairro"
                value="<?= $lf('bairro') ?>"
                placeholder="Centro">
        </div>
        <div class="col-md-3">
            <label for="classificacao_endereco_id" class="form-label">Tipo de Logradouro</label>
            <select class="form-select form-select-sm" id="classificacao_endereco_id" name="classificacao_endereco_id">
                <option value="">Selecione</option>
                <?php foreach ($classificacoes as $c) : ?>
                <option value="<?= (int)$c->id ?>" <?= $lselected('classificacao_endereco_id', $c->id) ?>>
                    <?= htmlspecialchars($c->descricao, ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="logradouro" class="form-label">Logradouro</label>
            <input type="text" class="form-control form-control-sm" id="logradouro" name="logradouro"
                value="<?= $lf('logradouro') ?>"
                placeholder="Rua das Flores">
        </div>
        <div class="col-md-3">
            <label for="numero" class="form-label">Numero</label>
            <input type="text" class="form-control form-control-sm" id="numero" name="numero"
                value="<?= $lf('numero') ?>"
                placeholder="123">
        </div>
        <div class="col-12">
            <label for="descricao_posicao" class="form-label">Complemento / Referencia</label>
            <input type="text" class="form-control form-control-sm" id="descricao_posicao" name="descricao_posicao"
                value="<?= $lf('descricao_posicao') ?>"
                placeholder="Proximo ao mercado, sala 2">
        </div>
    </div>

    <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-globe me-2"></i>Geolocalizacao <small class="text-muted">(opcional)</small></h5>
    <div class="row g-3 mb-4">
        <div class="col-md-5">
            <label for="latitude" class="form-label">Latitude</label>
            <input type="text" class="form-control form-control-sm" id="latitude" name="latitude"
                value="<?= $lf('latitude') ?>"
                placeholder="-23.3103">
        </div>
        <div class="col-md-5">
            <label for="longitude" class="form-label">Longitude</label>
            <input type="text" class="form-control form-control-sm" id="longitude" name="longitude"
                value="<?= $lf('longitude') ?>"
                placeholder="-51.1628">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="button" id="btnBuscarCoordenadas" class="btn btn-outline-info btn-sm w-100" title="Buscar coordenadas pelo endereço preenchido">
                <i class="fas fa-map-pin me-1"></i>Geo
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col d-flex justify-content-between">
            <a href="?page=listar_locais" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Cancelar
            </a>
            <button type="submit" name="submit" class="btn <?= $isEdit ? 'btn-warning' : 'btn-primary' ?> px-4">
                <i class="fas fa-save me-1"></i><?= $isEdit ? 'Salvar Alteracoes' : 'Salvar Local' ?>
            </button>
        </div>
    </div>
</form>
