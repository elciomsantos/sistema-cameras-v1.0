<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['export'] ?? '') !== 'csv') {
    header('Location: index.php?page=listar_locais');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$locaisService = new LocaisService();

$filters = [];
if (!empty($_POST['filters'])) {
    $parsed = [];
    $input = $_POST['filters'];
    parse_str($input, $parsed);
    $filters = [
        'search' => trim($parsed['busca'] ?? ''),
        'tipo_local_id' => preg_replace('/[^0-9]/', '', $parsed['tipo_local_id'] ?? ''),
        'camera' => in_array(trim($parsed['camera'] ?? ''), ['sim', 'nao', ''], true) ? trim($parsed['camera'] ?? '') : '',
    ];
}

$allLocais = $locaisService->searchAll($filters);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="locais_' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($output, ['Nome', 'Tipo', 'Regiao', 'Logradouro', 'Numero', 'Bairro', 'Cidade', 'UF', 'CEP', 'Secretaria', 'Tem Alarme', 'Cameras', 'Outros Equip.', 'Horario Funcionamento']);
foreach ($allLocais as $l) {
    fputcsv($output, [
        $l->nome ?? '',
        $l->tipo_local_nome ?? '',
        $l->regiao_nome ?? '',
        $l->logradouro ?? '',
        $l->numero ?? '',
        $l->bairro ?? '',
        $l->cidade ?? '',
        $l->uf ?? '',
        $l->cep ?? '',
        $l->sigla ?? '',
        ($l->tem_alarme ?? 0) ? 'Sim' : 'Nao',
        (int)($l->total_cameras ?? 0),
        (int)($l->total_outros ?? 0),
        $l->horario_funcionamento ?? '',
    ]);
}
fclose($output);
exit;
