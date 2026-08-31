<?php
use App\Services\RelatorioTurnoService;
use App\Services\UsuarioAnotacaoService;

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
$anotacaoService = new UsuarioAnotacaoService();
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

        case 'mudar_situacao':
            $registroId = filter_input(INPUT_POST, 'registro_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$registroId) {
                throw new RuntimeException('Registro inválido.');
            }
            $situacao = trim((string)($_POST['situacao'] ?? ''));
            $service->atualizarSituacao($registroId, $situacao, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Situação atualizada.';
            $redirecionar(['data' => $_POST['data'] ?? '', 'turno' => $_POST['turno'] ?? '']);

        case 'excluir_registro':
            $registroId = filter_input(INPUT_POST, 'registro_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$registroId) {
                throw new RuntimeException('Registro inválido.');
            }
            $service->excluirRegistro($registroId, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Registro excluído.';
            $redirecionar(['data' => $_POST['data'] ?? '', 'turno' => $_POST['turno'] ?? '']);

        case 'situacao_geral':
            $relatorioId = filter_input(INPUT_POST, 'relatorio_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$relatorioId) {
                throw new RuntimeException('Relatório inválido.');
            }
            $texto = trim((string)($_POST['texto'] ?? ''));
            $service->atualizarSituacaoGeral($relatorioId, $texto, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Situação geral salva.';
            $_SESSION['campo_limpar'] = 'situacao_geral';
            $redirecionar();

        case 'passagem_turno':
            $relatorioId = filter_input(INPUT_POST, 'relatorio_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$relatorioId) {
                throw new RuntimeException('Relatório inválido.');
            }
            $texto = trim((string)($_POST['texto'] ?? ''));
            $service->atualizarPassagemTurno($relatorioId, $texto, $usuarioId, $secretariaId);
            $_SESSION['sucesso'] = 'Passagem de turno salva.';
            $_SESSION['campo_limpar'] = 'passagem_turno';
            $redirecionar();

        // ====================================================
        // Anotações pessoais do usuário (privadas, só o dono vê)
        // ====================================================
        case 'anotacao_criar':
            $anotacaoService->criar($_POST, $usuarioId);
            $_SESSION['sucesso'] = 'Anotação adicionada.';
            $redirecionar(['anotacoes' => $_POST['anotacoes'] ?? '']);

        case 'anotacao_editar':
            $anotacaoId = filter_input(INPUT_POST, 'anotacao_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$anotacaoId) {
                throw new RuntimeException('Anotação inválida.');
            }
            $anotacaoService->atualizar($anotacaoId, $_POST, $usuarioId);
            $_SESSION['sucesso'] = 'Anotação atualizada.';
            $redirecionar(['anotacoes' => $_POST['anotacoes'] ?? '']);

        case 'anotacao_mudar_status':
            $anotacaoId = filter_input(INPUT_POST, 'anotacao_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$anotacaoId) {
                throw new RuntimeException('Anotação inválida.');
            }
            $status = trim((string)($_POST['status'] ?? ''));
            $anotacaoService->mudarStatus($anotacaoId, $status, $usuarioId);
            $_SESSION['sucesso'] = 'Status da anotação atualizado.';
            $redirecionar(['anotacoes' => $_POST['anotacoes'] ?? '']);

        case 'anotacao_arquivar':
            $anotacaoId = filter_input(INPUT_POST, 'anotacao_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$anotacaoId) {
                throw new RuntimeException('Anotação inválida.');
            }
            $arquivar = (int)($_POST['arquivar'] ?? 1) === 1;
            $anotacaoService->arquivar($anotacaoId, $arquivar, $usuarioId);
            $_SESSION['sucesso'] = $arquivar ? 'Anotação arquivada.' : 'Anotação reativada.';
            $redirecionar(['anotacoes' => $_POST['anotacoes'] ?? '']);

        case 'anotacao_excluir':
            $anotacaoId = filter_input(INPUT_POST, 'anotacao_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$anotacaoId) {
                throw new RuntimeException('Anotação inválida.');
            }
            $anotacaoService->excluir($anotacaoId, $usuarioId);
            $_SESSION['sucesso'] = 'Anotação excluída.';
            $redirecionar(['anotacoes' => $_POST['anotacoes'] ?? '']);

        default:
            throw new RuntimeException('Ação inválida.');
    }
} catch (Throwable $e) {
    $_SESSION['erro'] = $e->getMessage();
    $redirecionar();
}