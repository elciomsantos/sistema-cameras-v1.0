<?php

use App\Api\ApiResponse;
use App\Api\RequestValidator;
use App\Services\NotificacaoService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    $validator = new RequestValidator($_GET);
    $validator->validate([
        'page_num' => 'numeric|min:1',
        'per_page' => 'numeric|min:1|max:100',
        'filtro_tipo' => 'string|max:50',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 1) {
        $perPage = 20;
    }
    if ($perPage > 100) {
        $perPage = 100;
    }
    $filtroTipo = trim((string)($_GET['filtro_tipo'] ?? ''));

    $notificacaoService = new NotificacaoService();

    $usuarioSecretariaId = getUsuarioSecretariaId();
    $nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
    $secretariaId = ($nivel === 'user' && $usuarioSecretariaId !== null) ? $usuarioSecretariaId : null;

    $notificacaoService->gerarPendencias($secretariaId);

    if (isset($_GET['apenas_count'])) {
        $count = $notificacaoService->getCountNaoLidas($secretariaId);
        ApiResponse::success(['count' => $count]);
    }

    $result = $notificacaoService->getAll($page, $perPage, $filtroTipo, $secretariaId);
    ApiResponse::paginated($result['data'], $result['page'], $result['per_page'], $result['total']);
} catch (Throwable $e) {
    logError('[api_notificacoes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao carregar notificações.');
}
