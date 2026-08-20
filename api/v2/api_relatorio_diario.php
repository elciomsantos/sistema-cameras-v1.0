<?php

use App\Api\ApiResponse;
use App\Api\RequestValidator;
use App\Services\RelatorioTurnoService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    $validator = new RequestValidator($_GET);
    $validator->validate([
        'data' => 'string|max:10',
        'turno' => 'string|max:20',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $data = trim((string)($_GET['data'] ?? ''));
    $turno = trim((string)($_GET['turno'] ?? ''));

    $service = new RelatorioTurnoService();

    $usuarioSecretariaId = getUsuarioSecretariaId();
    $nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
    $secretariaId = ($nivel === 'user' && $usuarioSecretariaId !== null) ? $usuarioSecretariaId : null;

    $turnoAtual = $service->turnoAtual();

    if ($data === '' || $turno === '') {
        $data = $turnoAtual['data'];
        $turno = $turnoAtual['turno'];
    }

    $dados = $service->consultarRelatorio($data, $turno, $secretariaId);

    ApiResponse::success([
        'data' => $data,
        'turno' => $turno,
        'turno_atual' => $turnoAtual,
        'relatorio' => $dados['relatorio'],
        'registros' => $dados['registros'],
        'resumo' => $dados['resumo'],
        'pendencias_continuidade' => $dados['pendencias_continuidade'],
        'historico' => $dados['historico'],
        'os_disponiveis' => $service->listarOsDisponiveis($secretariaId),
    ]);
} catch (Throwable $e) {
    logError('[api_relatorio_diario] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao carregar relatório diário.');
}