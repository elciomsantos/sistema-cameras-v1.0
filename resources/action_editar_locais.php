<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('supervisor');

$error = FormValidator::checkSubmission();
if ($error) {
    $_SESSION['mensagem_erro_editar_local'] = $error;
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

$v = new FormValidator($_POST);
$v->required('nome', 'Nome do Local')
   ->required('tipo_local_id', 'Tipo de Local')
   ->inArray('uf', $ufs, 'UF')
   ->email('email')
   ->numeric('latitude', 'Latitude')
   ->numeric('longitude', 'Longitude');

$nome = mb_strtoupper(trim($v->get('nome', '')));
$tipo_local_id = $v->get('tipo_local_id');
$regiao_id = $v->get('regiao_id');
$secretaria_id = $v->get('secretaria_id');
$horario_funcionamento = mb_strtoupper(trim($v->get('horario_funcionamento', '')));
$telefone = trim($v->get('telefone', ''));
$tem_alarme = $v->get('tem_alarme') ? 1 : 0;
$alarme_conta = $tem_alarme ? ((int)($v->get('alarme_conta', 0)) ?: null) : null;
$cep = preg_replace('/\D+/', '', (string)($v->get('cep', '')));
$uf = strtoupper(trim($v->get('uf', '')));
$cidade = mb_strtoupper(trim($v->get('cidade', '')));
$bairro = mb_strtoupper(trim($v->get('bairro', '')));
$classificacao_endereco_id = $v->get('classificacao_endereco_id');
$logradouro = mb_strtoupper(trim($v->get('logradouro', '')));
$numero = trim($v->get('numero', ''));
$descricao_posicao = mb_strtoupper(trim($v->get('descricao_posicao', '')));
$latitude = trim($v->get('latitude', ''));
$longitude = trim($v->get('longitude', ''));

if ($v->fails()) {
    $_SESSION['mensagem_erro_editar_local'] = $v->firstError();
} else {
    $checkDup = $locaisService->checkDuplicado($nome, $secretaria_id ? (int)$secretaria_id : null, $id_local);
    if ($checkDup) {
        $_SESSION['mensagem_erro_editar_local'] = 'Ja existe um local com este nome nesta secretaria.';
    } else {
        $updateData = [
            'tipo_local_id' => $tipo_local_id ? (int)$tipo_local_id : null,
            'nome' => $nome,
            'regiao_id' => $regiao_id ? (int)$regiao_id : null,
            'secretaria_id' => $secretaria_id ? (int)$secretaria_id : null,
            'horario_funcionamento' => $horario_funcionamento ?: null,
            'telefone' => $telefone ?: null,
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
        }
        $_SESSION['mensagem_erro_editar_local'] = 'Erro ao atualizar local.';
    }
}

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
