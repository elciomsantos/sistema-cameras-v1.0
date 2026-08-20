<?php
use App\Services\RelatorioTurnoService;

require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('user');

$db = db();
$service = new RelatorioTurnoService($db);

$usuarioId = (int)($_SESSION['usuario']->id ?? 0);
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
$secretariaId = ($nivel === 'user') ? getUsuarioSecretariaId() : null;

$sucesso = $_SESSION['sucesso'] ?? '';
$erro = $_SESSION['erro'] ?? '';
$campoLimpar = $_SESSION['campo_limpar'] ?? '';
unset($_SESSION['sucesso'], $_SESSION['erro'], $_SESSION['campo_limpar']);

$dataSelecionada = trim((string)($_GET['data'] ?? ''));
$turnoSelecionado = trim((string)($_GET['turno'] ?? ''));
$turnoAtualInfo = $service->turnoAtual();

if ($dataSelecionada === '' || $turnoSelecionado === '') {
    $dataSelecionada = $turnoAtualInfo['data'];
    $turnoSelecionado = $turnoAtualInfo['turno'];
}

$relatorio = null;
$registros = [];
$resumo = null;
$pendencias = [];
$historico = [];

try {
    $dados = $service->consultarRelatorio($dataSelecionada, $turnoSelecionado, $secretariaId);
    $relatorio = $dados['relatorio'];
    $registros = $dados['registros'];
    $resumo = $dados['resumo'];
    $pendencias = $dados['pendencias_continuidade'];
    $historico = $dados['historico'];

    if ($relatorio === null) {
        $relatorio = $service->abrirRelatorio($dataSelecionada, $turnoSelecionado, $usuarioId, $secretariaId);
        $dados = $service->consultarRelatorio($dataSelecionada, $turnoSelecionado, $secretariaId);
        $registros = $dados['registros'];
        $resumo = $dados['resumo'];
        $pendencias = $dados['pendencias_continuidade'];
        $historico = $dados['historico'];
    }

    $osDisponiveis = $service->listarOsDisponiveis($secretariaId);
} catch (Throwable $e) {
    $erro = $erro !== '' ? $erro : 'Falha ao carregar o relatório de turno.';
    logError('[relatorio_diario] ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    $osDisponiveis = [];
}

$tipos = RelatorioTurnoService::TIPOS;
$situacoes = RelatorioTurnoService::SITUACOES;
$prioridades = RelatorioTurnoService::PRIORIDADES;

vite_page_js('relatorio-diario');
?>
<div class="container mt-4 mb-4 relatorio-diario-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-clipboard-list me-2"></i>Relatório Diário de Turno
        </h1>
        <a href="?page=home" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <?php if ($sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>
    <?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>

    <!-- Navegação por data/turno -->
    <form method="get" action="?page=relatorio_diario" class="card card-body mb-4 shadow-sm">
        <input type="hidden" name="page" value="relatorio_diario">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="dataSelecao" class="form-label">Data</label>
                <input type="date" class="form-control" id="dataSelecao" name="data"
                       value="<?= htmlspecialchars($dataSelecionada, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label for="turnoSelecao" class="form-label">Turno</label>
                <select class="form-select" id="turnoSelecao" name="turno">
                    <option value="DIURNO" <?= $turnoSelecionado === 'DIURNO' ? 'selected' : '' ?>>Diurno (07:00 às 19:00)</option>
                    <option value="NOTURNO" <?= $turnoSelecionado === 'NOTURNO' ? 'selected' : '' ?>>Noturno (19:00 às 07:00)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Consultar
                </button>
            </div>
            <div class="col-md-3">
                <a href="?page=relatorio_diario" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-clock me-1"></i>Turno atual
                </a>
            </div>
        </div>
    </form>

    <?php if ($relatorio): ?>
    <?php $ehTurnoAtual = ($relatorio->data === $turnoAtualInfo['data'] && $relatorio->turno === $turnoAtualInfo['turno']); ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Resumo do turno -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-sun me-2"></i>Relatório do Turno</h5>
                    <?php if ($ehTurnoAtual): ?>
                    <span class="badge bg-success">Turno atual</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <strong>Data:</strong>
                            <div><?= date('d/m/Y', strtotime($relatorio->data)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <strong>Turno:</strong>
                            <div><?= htmlspecialchars($relatorio->turno, ENT_QUOTES, 'UTF-8') ?>
                                (<?= htmlspecialchars($relatorio->hora_inicio, ENT_QUOTES, 'UTF-8') ?> às <?= htmlspecialchars($relatorio->hora_fim, ENT_QUOTES, 'UTF-8') ?>)</div>
                        </div>
                        <div class="col-md-4">
                            <strong>Operador:</strong>
                            <div><?= htmlspecialchars($relatorio->operador_nome ?? $relatorio->operador_usuario, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>

                    <hr>

                    <div class="row text-center g-2">
                        <div class="col">
                            <div class="border rounded py-2">
                                <div class="fs-4 fw-bold"><?= (int)$resumo['total'] ?></div>
                                <small class="text-muted">Ocorrências</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded py-2 bg-success-subtle">
                                <div class="fs-4 fw-bold text-success"><?= (int)$resumo['resolvidos'] ?></div>
                                <small class="text-success">Resolvidas</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded py-2 bg-warning-subtle">
                                <div class="fs-4 fw-bold text-warning"><?= (int)$resumo['pendentes'] ?></div>
                                <small class="text-warning">Pendentes</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded py-2 bg-info-subtle">
                                <div class="fs-4 fw-bold text-info"><?= (int)$resumo['acompanhamento'] ?></div>
                                <small class="text-info">Em acompanhamento</small>
                            </div>
                        </div>
                        <?php if ((int)$resumo['urgentes'] > 0): ?>
                        <div class="col">
                            <div class="border rounded py-2 bg-danger-subtle">
                                <div class="fs-4 fw-bold text-danger"><?= (int)$resumo['urgentes'] ?></div>
                                <small class="text-danger">Urgentes</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($ehTurnoAtual): ?>
                    <!-- Situação geral -->
                    <form method="post" action="?page=action_relatorio_diario" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="situacao_geral">
                        <input type="hidden" name="relatorio_id" value="<?= (int)$relatorio->id ?>">
                        <label for="situacaoGeral" class="form-label"><i class="fas fa-list-check me-1"></i>Situação geral do turno</label>
                        <textarea class="form-control" id="situacaoGeral" name="texto" rows="2"
                                  placeholder="Resumo geral do turno..."<?= $campoLimpar === 'situacao_geral' ? ' data-limpar="1"' : '' ?>><?= htmlspecialchars($relatorio->situacao_geral ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <button type="submit" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="fas fa-save me-1"></i>Salvar situação geral
                        </button>
                    </form>

                    <!-- Passagem de turno -->
                    <form method="post" action="?page=action_relatorio_diario" class="mt-4 border-top pt-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="passagem_turno">
                        <input type="hidden" name="relatorio_id" value="<?= (int)$relatorio->id ?>">
                        <h6 class="text-primary"><i class="fas fa-arrow-right me-1"></i>Passagem para o próximo turno</h6>
                        <textarea class="form-control" id="passagemTurno" name="texto" rows="3"
                                  placeholder="Informações gerais para o operador do próximo turno..."<?= $campoLimpar === 'passagem_turno' ? ' data-limpar="1"' : '' ?>><?= htmlspecialchars($relatorio->passagem_turno ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <button type="submit" class="btn btn-primary btn-sm mt-2">
                            <i class="fas fa-paper-plane me-1"></i>Salvar passagem de turno
                        </button>
                    </form>
                    <?php elseif (!empty($relatorio->passagem_turno)): ?>
                    <div class="alert alert-primary mt-4 mb-0">
                        <h6 class="alert-heading"><i class="fas fa-arrow-right me-1"></i>Passagem de turno</h6>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($relatorio->passagem_turno, ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Registros -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Registros do Turno</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($registros)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Nenhum registro neste turno ainda.</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($registros as $reg): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="badge bg-secondary"><?= htmlspecialchars($reg->horario, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($reg->tipo, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php
                                        $badgeSituacao = match ($reg->situacao) {
                                            'Resolvido' => 'bg-success',
                                            'Pendente' => 'bg-warning text-dark',
                                            'Em acompanhamento' => 'bg-info text-dark',
                                            default => 'bg-secondary',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeSituacao ?>"><?= htmlspecialchars($reg->situacao, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php
                                        $badgePri = match ($reg->prioridade) {
                                            'Urgente' => 'bg-danger',
                                            'Atenção' => 'bg-warning text-dark',
                                            default => 'bg-secondary',
                                        };
                                        ?>
                                        <span class="badge <?= $badgePri ?>"><?= htmlspecialchars($reg->prioridade, ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong class="ms-1"><?= htmlspecialchars($reg->titulo, ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <?php if ($reg->descricao): ?>
                                    <p class="mb-1"><?= nl2br(htmlspecialchars($reg->descricao, ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                    <?php if ($reg->equipamento_id): ?>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-video me-1"></i>Equipamento:
                                        <?= htmlspecialchars(trim(($reg->equip_patrimonio ?? '') . ' ' . ($reg->equip_descricao ?? '')), ENT_QUOTES, 'UTF-8') ?: ('#' . (int)$reg->equipamento_id) ?>
                                    </small>
                                    <?php endif; ?>
                                    <?php if ($reg->numero_os): ?>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-wrench me-1"></i>O.S.:
                                        <a href="?page=manutencao_cameras" class="text-decoration-none">#<?= htmlspecialchars($reg->numero_os, ENT_QUOTES, 'UTF-8') ?></a>
                                    </small>
                                    <?php endif; ?>
                                    <?php if ($reg->observacao): ?>
                                    <small class="text-muted d-block"><i class="fas fa-sticky-note me-1"></i><?= nl2br(htmlspecialchars($reg->observacao, ENT_QUOTES, 'UTF-8')) ?></small>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($reg->operador_nome ?? 'Operador', ENT_QUOTES, 'UTF-8') ?>
                                        <span class="ms-2"><i class="fas fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($reg->created_at)) ?></span>
                                    </small>
                                </div>
                                <?php if ($ehTurnoAtual && (isset($_SESSION['usuario']) && ((int)$reg->created_by === $usuarioId || in_array($nivel, ['admin', 'supervisor'], true)))): ?>
                                <div class="d-flex flex-column gap-1 flex-shrink-0">
                                    <form method="post" action="?page=action_relatorio_diario" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="acao" value="mudar_situacao">
                                        <input type="hidden" name="registro_id" value="<?= (int)$reg->id ?>">
                                        <input type="hidden" name="data" value="<?= htmlspecialchars($dataSelecionada, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="turno" value="<?= htmlspecialchars($turnoSelecionado, ENT_QUOTES, 'UTF-8') ?>">
                                        <select class="form-select form-select-sm" name="situacao" aria-label="Alterar situação">
                                            <?php foreach ($situacoes as $sit): ?>
                                            <option value="<?= $sit ?>" <?= $reg->situacao === $sit ? 'selected' : '' ?>><?= $sit ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <form method="post" action="?page=action_relatorio_diario" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="acao" value="excluir_registro">
                                        <input type="hidden" name="registro_id" value="<?= (int)$reg->id ?>">
                                        <input type="hidden" name="data" value="<?= htmlspecialchars($dataSelecionada, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="turno" value="<?= htmlspecialchars($turnoSelecionado, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulário de registro -->
            <?php if ($ehTurnoAtual): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Registrar acontecimento</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="?page=action_relatorio_diario" id="formRegistroRelatorio">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="criar_registro">
                        <input type="hidden" name="relatorio_id" value="<?= (int)$relatorio->id ?>">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="regHorario" class="form-label">Horário</label>
                                <input type="time" class="form-control" id="regHorario" name="horario" value="<?= date('H:i') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="regTipo" class="form-label">Tipo</label>
                                <select class="form-select" id="regTipo" name="tipo" required>
                                    <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="regSituacao" class="form-label">Situação</label>
                                <select class="form-select" id="regSituacao" name="situacao">
                                    <?php foreach ($situacoes as $sit): ?>
                                    <option value="<?= $sit ?>"><?= $sit ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="regPrioridade" class="form-label">Prioridade</label>
                                <select class="form-select" id="regPrioridade" name="prioridade">
                                    <?php foreach ($prioridades as $pri): ?>
                                    <option value="<?= $pri ?>"><?= $pri ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="regTitulo" class="form-label">Título</label>
                                <input type="text" class="form-control" id="regTitulo" name="titulo" maxlength="255" required>
                            </div>
                            <div class="col-md-6">
                                <label for="regEquipamento" class="form-label">Equipamento relacionado</label>
                                <select class="form-select" id="regEquipamento" name="equipamento_id">
                                    <option value="">Nenhum</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="regDescricao" class="form-label">Descrição</label>
                                <textarea class="form-control" id="regDescricao" name="descricao" rows="2" maxlength="5000"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="regOs" class="form-label">O.S. relacionada (opcional)</label>
                                <select class="form-select" id="regOs" name="os_id">
                                    <option value="">Nenhuma</option>
                                    <?php foreach ($osDisponiveis as $os): ?>
                                    <option value="<?= (int)$os->os_id ?>" data-os-tipo="<?= htmlspecialchars($os->os_tipo, ENT_QUOTES, 'UTF-8') ?>"
                                            data-numero-os="<?= htmlspecialchars($os->numero_os, ENT_QUOTES, 'UTF-8') ?>">
                                        #<?= htmlspecialchars($os->numero_os ?: ('OS-' . (int)$os->os_id), ENT_QUOTES, 'UTF-8') ?>
                                        — <?= htmlspecialchars(trim(($os->patrimonio ?? '') . ' ' . ($os->equip_descricao ?? '')), ENT_QUOTES, 'UTF-8') ?: 'Equipamento' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="os_tipo" id="osTipoHidden" value="">
                                <input type="hidden" name="numero_os" id="numeroOsHidden" value="">
                            </div>
                            <div class="col-12">
                                <label for="regObservacao" class="form-label">Observação</label>
                                <textarea class="form-control" id="regObservacao" name="observacao" rows="2" maxlength="5000"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Registrar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Pendências para continuidade -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning-subtle">
                    <h5 class="mb-0"><i class="fas fa-flag me-2"></i>Pendências / Em acompanhamento</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendencias)): ?>
                    <p class="text-muted mb-0">Nenhuma pendência neste turno.</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($pendencias as $pend): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <?php
                                    $badgePend = $pend->situacao === 'Pendente' ? 'bg-warning text-dark' : 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?= $badgePend ?>"><?= htmlspecialchars($pend->situacao, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($pend->prioridade === 'Urgente'): ?>
                                    <span class="badge bg-danger">Urgente</span>
                                    <?php endif; ?>
                                    <div class="mt-1"><strong><?= htmlspecialchars($pend->titulo, ENT_QUOTES, 'UTF-8') ?></strong></div>
                                    <?php if ($pend->numero_os): ?>
                                    <small class="text-muted">O.S. #<?= htmlspecialchars($pend->numero_os, ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Histórico -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Histórico de Turnos</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($historico)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted mb-0">Nenhum relatório anterior.</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($historico as $h): ?>
                        <?php
                        $link = '?page=relatorio_diario&data=' . urlencode($h->data) . '&turno=' . urlencode($h->turno);
                        $ativo = ($h->data === $dataSelecionada && $h->turno === $turnoSelecionado);
                        ?>
                        <a href="<?= $link ?>" class="list-group-item list-group-item-action <?= $ativo ? 'active' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= date('d/m/Y', strtotime($h->data)) ?></strong>
                                    <span class="badge bg-secondary ms-1"><?= htmlspecialchars($h->turno, ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($h->operador_nome ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        — <?= (int)$h->total ?> ocorrência(s)
                                    </div>
                                </div>
                                <div class="text-end small">
                                    <div><span class="text-success"><?= (int)$h->resolvidos ?></span> resolvidos</div>
                                    <div><span class="text-warning"><?= (int)$h->pendentes ?></span> pendentes</div>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>Não foi possível abrir o relatório para esta data/turno.
    </div>
    <?php endif; ?>
</div>