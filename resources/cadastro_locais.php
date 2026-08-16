<?php
use App\Services\LocaisService;
use App\Services\LookupService;

require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$db = db();
$locaisService = new LocaisService($db);
$lookupService = new LookupService($db);

$tiposLocais = $locaisService->getAllTiposLocais();
$secretarias = $locaisService->getAllSecretarias();
$classificacoes = $locaisService->getAllClassificacoesEnderecos();
$regioes = $locaisService->getAllRegioes();
$contasAlarmes = $lookupService->getContasAlarmes();

$usuarioSecretariaId = getUsuarioSecretariaId();
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
$isScopedUser = $nivel !== 'admin' && $usuarioSecretariaId !== null;

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$formData = $_SESSION['form_data_cadastro_local'] ?? [];
unset($_SESSION['form_data_cadastro_local']);

$mensagem_erro = $_SESSION['mensagem_erro_cadastro_local'] ?? '';
$mensagem_sucesso = $_SESSION['mensagem_sucesso_cadastro_local'] ?? '';
unset($_SESSION['mensagem_erro_cadastro_local'], $_SESSION['mensagem_sucesso_cadastro_local']);
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Cadastro de Local</h4>
                    <a href="?page=listar_locais" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($mensagem_erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($mensagem_sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($mensagem_sucesso, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php $modo = 'cadastro'; include __DIR__ . '/../inc/partials/locais-form.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($mensagem_sucesso): ?>
<?php $autofocusTipo = true; ?>
<?php endif; ?>

<?php vite_page_js('cadastro-locais'); ?>
