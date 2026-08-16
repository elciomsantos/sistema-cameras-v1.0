<?php
use App\Services\NotificacaoService;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=notificacoes');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['erro'] = 'Token CSRF inválido.';
    header('Location: index.php?page=notificacoes');
    exit;
}

$notificacaoService = new NotificacaoService();

try {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'marcar_lida') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            throw new RuntimeException('ID da notificação inválido.');
        }
        if ($notificacaoService->marcarLida($id)) {
            $_SESSION['sucesso'] = 'Notificação marcada como lida.';
        } else {
            throw new RuntimeException('Erro ao marcar notificação como lida.');
        }
    } elseif ($acao === 'marcar_todas_lidas') {
        $usuarioSecretariaId = getUsuarioSecretariaId();
        $nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
        $secretariaId = ($nivel === 'user' && $usuarioSecretariaId !== null) ? $usuarioSecretariaId : null;
        if ($notificacaoService->marcarTodasLidas($secretariaId)) {
            $_SESSION['sucesso'] = 'Todas as notificações foram marcadas como lidas.';
        } else {
            throw new RuntimeException('Erro ao marcar notificações como lidas.');
        }
    } else {
        throw new RuntimeException('Ação inválida.');
    }
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
}

header('Location: index.php?page=notificacoes');
exit;
