<?php
use App\Services\LocaisService;

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
        'nome' => trim($parsed['nome'] ?? ''),
        'regiao_id' => preg_replace('/[^0-9]/', '', $parsed['regiao_id'] ?? ''),
        'tipo_local_id' => preg_replace('/[^0-9]/', '', $parsed['tipo_local_id'] ?? ''),
        'tem_alarme' => in_array(trim($parsed['tem_alarme'] ?? ''), ['sim', 'nao', ''], true) ? trim($parsed['tem_alarme'] ?? '') : '',
        'camera' => in_array(trim($parsed['camera'] ?? ''), ['sim', 'nao', ''], true) ? trim($parsed['camera'] ?? '') : '',
    ];
}
$usuarioSecretariaId = getUsuarioSecretariaId();
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
if ($nivel !== 'admin' && $usuarioSecretariaId !== null) {
    $filters['secretaria_id'] = $usuarioSecretariaId;
}

$allLocais = $locaisService->streamLocaisExport($filters);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="locais_' . date('Y-m-d') . '.csv"');
$sanitizeCsv = function ($value) {
    if ($value === null || $value === '') return '';
    $first = $value[0];
    if (in_array($first, ['=', '+', '-', '@', "\t", "\n", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
};

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($output, ['Nome', 'Tipo', 'Regiao', 'Logradouro', 'Numero', 'Bairro', 'Cidade', 'UF', 'CEP', 'Secretaria', 'Tem Alarme', 'Tem Camera', 'Cameras', 'Outros Equip.', 'Horario Funcionamento']);
foreach ($allLocais as $l) {
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
