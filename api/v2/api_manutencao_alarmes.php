<?php

use App\Api\ApiResponse;
use App\Exception\ServiceException;
use App\Services\ManutencaoService;

require_once __DIR__ . '/api_manutencao_utils.php';

$config = require __DIR__ . '/manutencao_config_alarmes.php';

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method !== 'GET' && $method !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Método não permitido.');
    }

    $db = db();
    $svc = new ManutencaoService($db, $config);

    if ($method === 'GET') {
        $alarmeId = max(0, (int)($_GET['alarme_id'] ?? $_GET['equipamento_id'] ?? 0));
        $page = max(1, (int)($_GET['page_num'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
        $busca = trim((string)($_GET['busca'] ?? ''));
        $sortBy = trim((string)($_GET['sort_by'] ?? 'data_hora'));
        $sortDir = trim((string)($_GET['sort_dir'] ?? 'DESC'));
        $includeLists = !isset($_GET['include_lists']) || (int)$_GET['include_lists'] !== 0;
        $dataInicial = trim((string)($_GET['data_inicial'] ?? ''));
        $dataFinal = trim((string)($_GET['data_final'] ?? ''));

        $cacheKeyParts = [
            'manut_alarmes',
            $alarmeId, $page, $perPage, $busca, $sortBy, $sortDir,
            $includeLists ? '1' : '0',
            $dataInicial, $dataFinal,
        ];
        $manutAlarmesCacheKey = 'manut_alarmes:' . md5(implode('|', $cacheKeyParts));

        if (function_exists('apcu_fetch') && empty($_GET['nocache'])) {
            $cached = apcu_fetch($manutAlarmesCacheKey);
            if (is_array($cached) && isset($cached['pagination'])) {
                ApiResponse::success($cached);
            }
        }

        $payload = $svc->loadPayload([
            'alarme_id' => $alarmeId,
            'page' => $page,
            'per_page' => $perPage,
            'busca' => $busca,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'include_lists' => $includeLists,
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
        ]);

        if (function_exists('apcu_store') && empty($_GET['nocache'])) {
            apcu_store($manutAlarmesCacheKey, $payload, 30);
        }

        ApiResponse::success($payload);
    }

    if ($method === 'POST') {
        $input = getInputPayload();
        $action = strtolower(trim((string)($input['action'] ?? 'create_os')));
        $usuarioId = (int)($_SESSION['usuario']->id ?? 0);

        if ($usuarioId <= 0) {
            ApiResponse::error('UNAUTHORIZED', 'Usuário inválido.');
        }

        if ($action === 'create_os') {
            $svc->createOs($input, $usuarioId);
            ApiResponse::created(['message' => 'Ordem de serviço cadastrada com sucesso.'], null);
        } elseif ($action === 'start_os') {
            $svc->startOs($input, $usuarioId);
            ApiResponse::success(['message' => 'Ordem de serviço em execução.']);
        } elseif ($action === 'finalize_os') {
            $svc->finalizeOs($input, $usuarioId);
            ApiResponse::success(['message' => 'Ordem de serviço marcada como realizada com sucesso.']);
        } else {
            ApiResponse::error('BAD_REQUEST', 'Ação da API não reconhecida.');
        }
    }
} catch (ServiceException $e) {
    ApiResponse::error($e->getErrorCode(), $e->getMessage());
} catch (Throwable $e) {
    if (isset($db) && method_exists($db, 'rollback')) {
        try {
            $db->rollback();
        } catch (Throwable $ignored) {
        }
    }
    error_log('[api_manutencao_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao processar manutenção de alarmes.');
}