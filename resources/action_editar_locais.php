<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';
require_once __DIR__ . '/../src/Services/LookupService.php';

requererAcesso('supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: index.php?page=listar_locais');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['mensagem_erro_editar_local'] = 'Falha de validacao CSRF.';
    header('Location: index.php?page=listar_locais');
    exit;
}

$id_local = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id_local) {
    $_SESSION['erro'] = 'ID do local invalido.';
    header('Location: index.php?page=listar_locais');
    exit;
}

$db = db();
$locaisService = new LocaisService($db);

$local = $locaisService->getById($id_local);
if (!$local) {
    $_SESSION['erro'] = 'Local nao encontrado.';
    header('Location: index.php?page=listar_locais');
    exit;
}

$ufs = [
    'AC','AL','AP','AM','BA','CE','DF','ES','GO',
    'MA','MT','MS','MG','PA','PB','PR','PE','PI',
    'RJ','RN','RS','RO','RR','SC','SP','SE','TO'
];

$tipo_local_id = $_POST['tipo_local_id'] ?? null;
$nome = mb_strtoupper(trim($_POST['nome'] ?? ''));
$regiao_id = $_POST['regiao_id'] ?? null;
$secretaria_id = $_POST['secretaria_id'] ?? null;
$horario_funcionamento = mb_strtoupper(trim($_POST['horario_funcionamento'] ?? ''));
$telefone = trim($_POST['telefone'] ?? '');
$email = trim($_POST['email'] ?? '');
$tem_alarme = isset($_POST['tem_alarme']) ? 1 : 0;
$alarme_conta = $tem_alarme ? ((int)($_POST['alarme_conta'] ?? 0) ?: null) : null;
$cep = preg_replace('/\D+/', '', (string)($_POST['cep'] ?? ''));
$uf = strtoupper(trim($_POST['uf'] ?? ''));
$cidade = mb_strtoupper(trim($_POST['cidade'] ?? ''));
$bairro = mb_strtoupper(trim($_POST['bairro'] ?? ''));
$classificacao_endereco_id = $_POST['classificacao_endereco_id'] ?? null;
$logradouro = mb_strtoupper(trim($_POST['logradouro'] ?? ''));
$numero = trim($_POST['numero'] ?? '');
$descricao_posicao = mb_strtoupper(trim($_POST['descricao_posicao'] ?? ''));
$latitude = trim($_POST['latitude'] ?? '');
$longitude = trim($_POST['longitude'] ?? '');

$mensagem_erro = '';
if (empty($nome)) {
    $mensagem_erro = 'O campo Nome do Local e obrigatorio.';
} elseif (empty($tipo_local_id)) {
    $mensagem_erro = 'Selecione o Tipo de Local.';
} else {
    $checkDup = $locaisService->checkDuplicado($nome, $secretaria_id ? (int)$secretaria_id : null, $id_local);
    if ($checkDup) {
        $mensagem_erro = 'Ja existe um local com este nome nesta secretaria.';
    } else {
        $updateData = [
            'tipo_local_id' => $tipo_local_id ? (int)$tipo_local_id : null,
            'nome' => $nome,
            'regiao_id' => $regiao_id ? (int)$regiao_id : null,
            'secretaria_id' => $secretaria_id ? (int)$secretaria_id : null,
            'horario_funcionamento' => $horario_funcionamento ?: null,
            'telefone' => $telefone ?: null,
            'email' => $email ?: null,
            'tem_alarme' => $tem_alarme,
            'alarme_conta' => $alarme_conta,
            'cep' => $cep ?: null,
            'uf' => $uf ?: null,
            'cidade' => $cidade ?: null,
            'bairro' => $bairro ?: null,
            'classificacao_endereco_id' => $classificacao_endereco_id ? (int)$classificacao_endereco_id : null,
            'logradouro' => $logradouro ?: null,
            'numero' => $numero ?: null,
            'descricao_posicao' => $descricao_posicao ?: null,
            'latitude' => $latitude !== '' ? (float)$latitude : null,
            'longitude' => $longitude !== '' ? (float)$longitude : null,
        ];
        enableAuditTriggers($db, 'web');
        $updated = $locaisService->update($id_local, $updateData);

        if ($updated) {
            $_SESSION['mensagem_sucesso_editar_local'] = 'Local atualizado com sucesso.';
            header('Location: index.php?page=editar_locais&id=' . $id_local);
            exit;
        } else {
            $mensagem_erro = 'Erro ao atualizar local.';
        }
    }
}

$_SESSION['mensagem_erro_editar_local'] = $mensagem_erro;
$_SESSION['form_data_editar_local'] = [
    'tipo_local_id' => $tipo_local_id,
    'nome' => $nome,
    'regiao_id' => $regiao_id,
    'secretaria_id' => $secretaria_id,
    'horario_funcionamento' => $horario_funcionamento,
    'tem_alarme' => $tem_alarme,
    'alarme_conta' => $alarme_conta,
    'cep' => $cep,
    'uf' => $uf,
    'cidade' => $cidade,
    'bairro' => $bairro,
    'classificacao_endereco_id' => $classificacao_endereco_id,
    'logradouro' => $logradouro,
    'numero' => $numero,
    'descricao_posicao' => $descricao_posicao,
    'latitude' => $latitude,
    'longitude' => $longitude,
];
header('Location: index.php?page=editar_locais&id=' . $id_local);
exit;
