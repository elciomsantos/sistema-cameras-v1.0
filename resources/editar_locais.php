<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('supervisor');

$db = db();
$locaisService = new LocaisService($db);

$mensagem_sucesso = $_SESSION['mensagem_sucesso_editar_local'] ?? '';
unset($_SESSION['mensagem_sucesso_editar_local']);

$id_local = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id_local) {
    $_SESSION['erro'] = 'ID do local invalido.';
    header('Location: index.php?page=listar_locais');
    exit;
}

$local = $locaisService->getById($id_local);
if (!$local) {
    $_SESSION['erro'] = 'Local nao encontrado.';
    header('Location: index.php?page=listar_locais');
    exit;
}

requererEscopoLocal($id_local);

$tiposLocais = $locaisService->getAllTiposLocais();
$secretarias = $locaisService->getAllSecretarias();
$classificacoes = $locaisService->getAllClassificacoesEnderecos();
$regioes = $locaisService->getAllRegioes();

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$mensagem_erro = $_SESSION['mensagem_erro_editar_local'] ?? '';
unset($_SESSION['mensagem_erro_editar_local']);

$formData = $_SESSION['form_data_editar_local'] ?? [];
unset($_SESSION['form_data_editar_local']);
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-pen me-2"></i>Editar Local</h4>
                    <a href="?page=listar_locais" class="btn btn-outline-dark btn-sm">
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

                    <?php $modo = 'editar'; $data = $local; include __DIR__ . '/../inc/partials/locais-form.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php vite_page_js('editar-locais'); ?>
