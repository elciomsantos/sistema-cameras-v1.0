<?php
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$lookup = new LookupService();
$dados = [];
$formData = [];

try {
    $dados = $lookup->getDadosEditarCamera();
} catch (Exception $e) {
    error_log('Erro ao carregar dados editar_cameras: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Erro ao carregar dados do formulario.</div>';
}
?>

<?php vite_page_css('editar-cameras'); ?>

<div class="container mt-4 mb-4">
    <div class="editar-camera-wrapper">
        <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Camera</h4>
                    <div class="page-subtitle">Localize a camera, revise os dados e salve as alteracoes do equipamento.</div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <a href="?page=controle_cameras" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                    <div class="camera-search-panel mb-3">
                    <div class="section-title"><i class="fas fa-search"></i><span>Selecionar camera</span></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="filtroBuscaCamera" class="form-label">Buscar camera</label>
                            <div class="input-group">
                                <input type="text" id="filtroBuscaCamera" class="form-control"
                                    placeholder="Local, IP ou tipo de câmera...">
                                <button type="button" class="btn btn-outline-primary" id="btnBuscarCamera">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="cameraSelector" class="form-label">Selecionar camera</label>
                            <select id="cameraSelector" class="form-select" size="10">
                                <option value="">Carregando cameras...</option>
                            </select>
                        </div>
                    </div>
                    </div>


                    <form id="formEditarCamera" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" id="camera_id">

                        <!-- Dados da Camera -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-camera"></i>
                                <span>Dados da Camera</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['status'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Procedimento</label>
                                <select name="procedimento_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['procedimentos'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Regiao</label>
                                <select name="regiao_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['regioes'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo Dispositivo</label>
                                <select name="tipo_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['tipos_equipamento'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo Câmera</label>
                                <select name="tipo_camera" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['tipo_cameras'] as $tc): ?>
                                        <option value="<?= htmlspecialchars((string)$tc->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tc->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Nome/Local</label>
                                <input type="text" name="nome_local" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Descrição do Local de Instalação</label>
                                <input type="text" name="descricao_posicao" class="form-control" maxlength="255"
                                    placeholder="Ex: Corredor Central, Sala 01">
                            </div>
                            </div></div></div>

                        <!-- Marca e Modelo -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-tag"></i>
                                <span>Marca e Modelo</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Marca</label>
                                <select name="marca_id" class="form-select" id="marcaSelect" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['marcas'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-center justify-content-center pt-30">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="toggleModeloExistente" checked>
                                    <label class="form-check-label label-compact" for="toggleModeloExistente">Usar modelo existente</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Modelo</label>
                                <div id="modeloContainer">
                                    <select name="modelo_existente" id="modeloExistenteSelect" class="form-select" required>
                                        <option value="">Selecione a marca primeiro...</option>
                                    </select>
                                    <input type="hidden" name="novo_modelo_nome" value="">
                                </div>
                            </div>
                            </div>

                            <?php $sectionPrefix = 'Edit'; $ckPrefix = 'edit'; include __DIR__ . '/../inc/partials/camera-especificacoes.php'; ?>

                            </div></div>

                        <!-- Localização e Vínculo -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-map-pin"></i>
                                <span>Localização e Vínculo</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Local</label>
                                <select name="local_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['locais'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Secretaria</label>
                                <select name="secretaria_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['secretarias'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Transmissao</label>
                                <select name="transmissao_id" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['transmissoes'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->tipo ?? $item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Origem do Link</label>
                                <select name="origem_link_id" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['origem_links'] as $item): ?>
                                        <option value="<?= htmlspecialchars((string)$item->id, ENT_QUOTES, 'UTF-8') ?>" data-inscricao="<?= htmlspecialchars($item->inscricao ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Inscricao</label>
                                <input type="text" name="inscricao" class="form-control" maxlength="20">
                            </div>
                            </div>
                            <div class="row g-3 mt-2">
                                <div class="col-12">
                                    <div class="alarme-check-wrapper" id="alarmeWrapper">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                name="tem_alarme" id="temAlarme" value="1">
                                            <label class="form-check-label" for="temAlarme">
                                                <i class="fas fa-bell me-2 text-warning"></i>
                                                Possui central de alarme no local
                                            </label>
                                        </div>
                                        <div id="containerAlarmeConta">
                                            <label class="form-label required mb-1 label-brown" for="alarmeConta">
                                                Número da conta do alarme
                                            </label>
                                            <input type="text" name="alarme_conta" id="alarmeConta"
                                                class="form-control"
                                                placeholder="Ex: 12345"
                                                maxlength="50"
                                                disabled>
                                            <div class="form-text form-text-brown">Número da conta cadastrada na empresa de monitoramento</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div></div>

                        <!-- Identificação -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-fingerprint"></i>
                                <span>Identificação</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">IP</label>
                                <input type="text" name="ip" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mosaico</label>
                                <input type="text" name="mosaico" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Serie/MAC</label>
                                <input type="text" name="serie_mac" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Patrimonio</label>
                                <input type="text" name="patrimonio" class="form-control">
                            </div>
                            </div></div></div>

                        <!-- Endereço -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Endereço</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">CEP</label>
                                <input type="text" name="cep" class="form-control" maxlength="9" placeholder="00000-000">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">UF</label>
                                <input type="text" name="uf" class="form-control" maxlength="2" placeholder="PR">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cidade" class="form-control" placeholder="Ex: Londrina">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bairro</label>
                                <input type="text" name="bairro" class="form-control" placeholder="Ex: Centro">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tipo Logradouro</label>
                                <select name="tipo_logradouro" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['classificacao_enderecos'] as $item): ?>
                                        <option value="<?= htmlspecialchars($item->descricao, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($item->descricao, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logradouro</label>
                                <input type="text" name="logradouro" class="form-control" placeholder="Ex: Brasil, XV de Novembro">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Numero</label>
                                <input type="text" name="numero" class="form-control" placeholder="Ex: 150">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complemento" class="form-control" placeholder="Ex: Predio A">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Coordenadas</label>
                                <input type="text" name="coordenadas" class="form-control" placeholder="Latitude, Longitude">
                            </div>
                            </div></div></div>

                        <!-- Datas e Observações -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Datas e Observações</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Data Instalacao</label>
                                <input type="date" name="data_instalacao" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observacao</label>
                                <textarea name="observacao" class="form-control" rows="3"></textarea>
                            </div>
                            </div></div></div>

                            <div class="col-12 mt-4 form-actions d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary" id="btnSubmit">
                                    <i class="fas fa-save me-1"></i>Salvar Alteracoes
                                </button>
                                <a href="?page=controle_cameras" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Voltar
                                </a>
                                <a href="?page=controle_cameras" class="btn btn-secondary">Cancelar</a>
                            </div>
                    </form>

                    <?php include __DIR__ . '/../inc/partials/anexos-section.php'; ?>
                </div>
        </div>
    </div>
</div>

<div id="loadingOverlay" class="loading-overlay overlay-fixed overlay-light is-hidden">
    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
</div>

<?php vite_page_js('editar-cameras'); ?>

