<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=listar_locais');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['erro'] = 'Token CSRF inválido.';
    header('Location: index.php?page=listar_locais');
    exit;
}

$id_local = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id_local) {
    $_SESSION['erro'] = 'ID do local inválido.';
    header('Location: index.php?page=listar_locais');
    exit;
}

requererEscopoLocal($id_local);

try {
    $locaisService = new LocaisService();
    $local = $locaisService->getById($id_local);
    if (!$local) {
        $_SESSION['erro'] = 'Local não encontrado.';
        header('Location: index.php?page=listar_locais');
        exit;
    }

    if ($locaisService->softDelete($id_local)) {
        $_SESSION['sucesso'] = 'Local excluído com sucesso.';
    } else {
        $_SESSION['erro'] = 'Erro ao excluir o local.';
    }
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao excluir o local: ' . $e->getMessage();
}

header('Location: index.php?page=listar_locais');
exit;
