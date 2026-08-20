<?php
use App\Services\RelatorioTurnoService;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=relatorio_diario');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['erro'] = 'Token CSRF inválido.';
    header('Location: index.php?page=relatorio_diario');
    exit;
}

$service = new RelatorioTurnoService();
$usuarioId = (int)($_SESSION['usuario']->id ?? 0);
$nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);
$usuarioSecretariaId = getUsuarioSecretariaId();
$secretariaId = ($nivel === 'user') ? $usuarioSecretariaId : null;

$redirecionar = function (array $extra = []) {
    $params = ['page' => 'relatorio_diario'];
    foreach ($extra as $k => $v) {
        if ($v !== null && $v !== '') {
            $params[$k] = $v;
        }
    }
    header('Location: index.php?' . http_build_query($params));
    exit;
};

try {
    $acao = $_POST['acao'] ?? '';

    switch ($acao) {
        case 'criar_registro':
            $relatorioId = filter_input(INPUT_POST, 'relatorio_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$relatorioId) {
                throw new RuntimeException('Relatório inválido.');
            }

            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $tipo = trim((string)($_POST['tipo'] ?? ''));
            $situacao = trim((string)($_POST['situacao'] ?? ''));
            $prioridade = trim((string)($_POST['prioridade'] ?? ''));
            $horario = trim((string)($_POST['horario'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $observacao = trim((string)($_POST['observacao'] ?? ''));
            $equipamentoId = filter_input(INPUT_POST, 'equipamento_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $osId = filter_input(INPUT_POST, 'os_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $osTipo = trim((string)($_POST['os_tipo'] ?? ''));
            $numeroOs = trim((string)($_POST['numero_os'] ?? ''));

            $service->criarRegistro([
                'relatorio_id' => $relatorioId,
                'horario' => $horario,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'situacao' => $situacao,
                'prioridade' => $prioridade,
                'equipamento_id' => $equipamentoId ?: null,
                'os_tipo' => $osTipo !== '' ? $osTipo : null,
                'os_id' => $osId ?: null,
                'numero_os' => $numeroOs !== '' ? $numeroOs : null,
                'observacao' => $observacao,
            ], $usuarioId, $secretariaId);

            $_SESSION['sucesso'] = 'Registro adicionado ao relatório.';
            $rel = $service->getRelatorioPorId($relatorioId);
            $redirecionar(['data' => $rel->data ?? '', 'turno' => $rel->turno ?? '']);
            break;

        case 'mudar_situacao':
            $registroId = filter_input(INPUT_POST, 'registro_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$registroId) {
                throw new RuntimeException('Registro inválido.');
            }
            $situacao = trim((string)($_POST['situacao'] ?? ''));
            $service->atualizarSituacao($registroId, $situacao, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Situação atualizada.';
            $redirecionar(['data' => $_POST['data'] ?? '', 'turno' => $_POST['turno'] ?? '']);
            break;

        case 'excluir_registro':
            $registroId = filter_input(INPUT_POST, 'registro_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$registroId) {
                throw new RuntimeException('Registro inválido.');
            }
            $service->excluirRegistro($registroId, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Registro excluído.';
            $redirecionar(['data' => $_POST['data'] ?? '', 'turno' => $_POST['turno'] ?? '']);
            break;

        case 'situacao_geral':
            $relatorioId = filter_input(INPUT_POST, 'relatorio_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$relatorioId) {
                throw new RuntimeException('Relatório inválido.');
            }
            $texto = trim((string)($_POST['texto'] ?? ''));
            $service->atualizarSituacaoGeral($relatorioId, $texto, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Situação geral salva.';
            $redirecionar();
            break;

        case 'passagem_turno':
            $relatorioId = filter_input(INPUT_POST, 'relatorio_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$relatorioId) {
                throw new RuntimeException('Relatório inválido.');
            }
            $texto = trim((string)($_POST['texto'] ?? ''));
            $service->atualizarPassagemTurno($relatorioId, $texto, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Passagem de turno salva.';
            $redirecionar();
            break;

        default:
            throw new RuntimeException('Ação inválida.');
    }
} catch (Throwable $e) {
    $_SESSION['erro'] = $e->getMessage();
    $redirecionar();
}