<?php
use App\Services\LocaisService;
use App\Validation\FormValidator;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('supervisor');

$error = FormValidator::checkSubmission();
if ($error) {
    $_SESSION['mensagem_erro_cadastro_local'] = $error;
    header('Location: index.php?page=cadastro_locais');
    exit;
}

$db = db();
$locaisService = new LocaisService($db);

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
$secretaria_id = $v->get('secretaria_id');

$usuarioSecretariaId = getUsuarioSecretariaId();
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
if ($nivel !== 'admin' && $usuarioSecretariaId !== null) {
    $secretaria_id = (string)$usuarioSecretariaId;
}
$regiao_id = $v->get('regiao_id');
$horario_funcionamento = mb_strtoupper(trim($v->get('horario_funcionamento', '')));
$telefone = trim($v->get('telefone', ''));
$ramal = trim($v->get('ramal', ''));
$tem_alarme = $v->get('tem_alarme') ? 1 : 0;
$alarme_conta = $tem_alarme ? ((int)($v->get('alarme_conta', 0)) ?: null) : null;
$tem_camera = $v->get('tem_camera') ? 1 : 0;
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
    $_SESSION['mensagem_erro_cadastro_local'] = $v->firstError();
} else {
    $checkDup = $locaisService->checkDuplicado($nome, $secretaria_id ? (int)$secretaria_id : null);
    if ($checkDup) {
        $_SESSION['mensagem_erro_cadastro_local'] = 'Ja existe um local com este nome nesta secretaria.';
    } else {
        $insertData = [
            'tipo_local_id' => $tipo_local_id ? (int)$tipo_local_id : null,
            'nome' => $nome,
            'regiao_id' => $regiao_id ? (int)$regiao_id : null,
            'secretaria_id' => $secretaria_id ? (int)$secretaria_id : null,
            'horario_funcionamento' => $horario_funcionamento ?: null,
            'telefone' => $telefone ?: null,
            'ramal' => $ramal ?: null,
            'tem_alarme' => $tem_alarme,
            'alarme_conta' => $alarme_conta,
            'tem_camera' => $tem_camera,
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
        try {
            $newId = $locaisService->create($insertData);
        } catch (InvalidArgumentException $e) {
            $_SESSION['mensagem_erro_cadastro_local'] = $e->getMessage();
            $_SESSION['form_data_cadastro_local'] = array_intersect_key($_POST, array_flip([
                'tipo_local_id', 'nome', 'regiao_id', 'secretaria_id',
                'horario_funcionamento', 'telefone', 'ramal', 'tem_alarme', 'alarme_conta', 'tem_camera', 'cep', 'uf',
                'cidade', 'bairro', 'classificacao_endereco_id',
                'logradouro', 'numero', 'descricao_posicao',
                'latitude', 'longitude',
            ]));
            header('Location: index.php?page=cadastro_locais');
            exit;
        }
        if ($newId > 0) {
            $_SESSION['mensagem_sucesso_cadastro_local'] = 'Local cadastrado com sucesso.';
            unset($_SESSION['form_data_cadastro_local']);
            header('Location: index.php?page=cadastro_locais');
            exit;
        }
        $_SESSION['mensagem_erro_cadastro_local'] = 'Erro ao cadastrar local.';
    }
}

$_SESSION['form_data_cadastro_local'] = array_intersect_key($_POST, array_flip([
    'tipo_local_id', 'nome', 'regiao_id', 'secretaria_id',
    'horario_funcionamento', 'telefone', 'ramal', 'tem_alarme', 'alarme_conta', 'tem_camera', 'cep', 'uf',
    'cidade', 'bairro', 'classificacao_endereco_id',
    'logradouro', 'numero', 'descricao_posicao',
    'latitude', 'longitude',
]));
header('Location: index.php?page=cadastro_locais');
exit;
