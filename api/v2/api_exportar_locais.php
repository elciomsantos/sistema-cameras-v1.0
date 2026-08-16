<?php

declare(strict_types=1);

$format = $_GET['format'] ?? 'json';
$format = in_array($format, ['json', 'csv'], true) ? $format : 'json';

$filters = [];
if (!empty($_GET['nome'])) {
    $filters['nome'] = trim($_GET['nome']);
}
if (!empty($_GET['regiao_id'])) {
    $filters['regiao_id'] = (int)$_GET['regiao_id'];
}
if (!empty($_GET['tipo_local_id'])) {
    $filters['tipo_local_id'] = (int)$_GET['tipo_local_id'];
}
if (!empty($_GET['tem_alarme'])) {
    $filters['tem_alarme'] = in_array($_GET['tem_alarme'], ['sim', 'nao'], true) ? $_GET['tem_alarme'] : '';
}
if (!empty($_GET['camera'])) {
    $filters['camera'] = in_array($_GET['camera'], ['sim', 'nao'], true) ? $_GET['camera'] : '';
}

$usuarioSecretariaId = getUsuarioSecretariaId();
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
if ($nivel !== 'admin' && $usuarioSecretariaId !== null) {
    $filters['secretaria_id'] = $usuarioSecretariaId;
}

$locaisService = new LocaisService();

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="locais_' . date('Y-m-d') . '.csv"');

    $sanitizeCsv = function ($value) {
        if ($value === null || $value === '') {
            return '';
        }
        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\n", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    };

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Nome', 'Tipo', 'Regiao', 'Logradouro', 'Numero', 'Bairro', 'Cidade', 'UF', 'CEP', 'Secretaria', 'Tem Alarme', 'Tem Camera', 'Cameras', 'Outros Equip.', 'Horario Funcionamento']);

    foreach ($locaisService->streamLocaisExport($filters) as $l) {
        fputcsv($output, [
            $sanitizeCsv($l->nome ?? ''),
            $sanitizeCsv($l->tipo_local_nome ?? ''),
            $sanitizeCsv($l->regiao_nome ?? ''),
            $sanitizeCsv($l->logradouro ?? ''),
            $sanitizeCsv($l->numero ?? ''),
            $sanitizeCsv($l->bairro ?? ''),
            $sanitizeCsv($l->cidade ?? ''),
            $sanitizeCsv($l->uf ?? ''),
            $sanitizeCsv($l->cep ?? ''),
            $sanitizeCsv($l->sigla ?? ''),
            $sanitizeCsv(($l->tem_alarme ?? 0) ? 'Sim' : 'Nao'),
            $sanitizeCsv(($l->tem_camera ?? 0) ? 'Sim' : 'Nao'),
            $sanitizeCsv((int)($l->total_cameras ?? 0)),
            $sanitizeCsv((int)($l->total_outros ?? 0)),
            $sanitizeCsv($l->horario_funcionamento ?? ''),
        ]);
    }
    fclose($output);
    exit;
}

$data = [];
foreach ($locaisService->streamLocaisExport($filters) as $l) {
    $data[] = [
        'id' => (int)$l->id,
        'nome' => $l->nome ?? '',
        'tipo_local' => $l->tipo_local_nome ?? '',
        'regiao' => $l->regiao_nome ?? '',
        'logradouro' => $l->logradouro ?? '',
        'numero' => $l->numero ?? '',
        'bairro' => $l->bairro ?? '',
        'cidade' => $l->cidade ?? '',
        'uf' => $l->uf ?? '',
        'cep' => $l->cep ?? '',
        'secretaria' => $l->sigla ?? '',
        'tem_alarme' => (bool)($l->tem_alarme ?? 0),
        'tem_camera' => (bool)($l->tem_camera ?? 0),
        'total_cameras' => (int)($l->total_cameras ?? 0),
        'total_outros' => (int)($l->total_outros ?? 0),
        'horario_funcionamento' => $l->horario_funcionamento ?? '',
    ];
}

ApiResponse::success(['locais' => $data, 'total' => count($data)]);
