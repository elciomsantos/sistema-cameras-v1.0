<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=admin_tabelas');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['erro'] = 'Token CSRF inválido.';
    header('Location: index.php?page=admin_tabelas');
    exit;
}

$allowedTables = ['secretarias', 'regioes', 'tipos_locais', 'tipos_equipamento', 'tipos_secretaria', 'marcas', 'status'];
$tabela = $_POST['tabela'] ?? '';
if (!in_array($tabela, $allowedTables, true)) {
    $_SESSION['erro'] = 'Tabela inválida.';
    header('Location: index.php?page=admin_tabelas');
    exit;
}

$acao = $_POST['acao'] ?? '';
if (!in_array($acao, ['criar', 'editar', 'excluir'], true)) {
    $_SESSION['erro'] = 'Ação inválida.';
    header('Location: index.php?page=admin_tabelas');
    exit;
}

$db = db();

try {
    if ($acao === 'criar' || $acao === 'editar') {
        $campos = $_POST['campos'] ?? [];

        if ($tabela === 'secretarias') {
            if (empty($campos['nome']) || empty($campos['sigla'])) {
                throw new RuntimeException('Nome e Sigla são obrigatórios.');
            }

            if ($acao === 'criar') {
                $check = $db->query("SELECT id FROM secretarias WHERE nome = ?", [trim($campos['nome'])]);
                if (!empty($check['data'])) {
                    throw new RuntimeException('Já existe uma secretaria com este nome.');
                }
                $checkSigla = $db->query("SELECT id FROM secretarias WHERE sigla = ?", [trim($campos['sigla'])]);
                if (!empty($checkSigla['data'])) {
                    throw new RuntimeException('Já existe uma secretaria com esta sigla.');
                }
            }

            $tipoId = !empty($campos['tipo_id']) ? (int)$campos['tipo_id'] : 1;
            $ativo = isset($campos['ativo']) ? 1 : 0;

            if ($acao === 'criar') {
                $db->query(
                    "INSERT INTO secretarias (nome, sigla, tipo_id, ativo) VALUES (?, ?, ?, ?)",
                    [trim($campos['nome']), trim($campos['sigla']), $tipoId, $ativo]
                );
            } else {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) throw new RuntimeException('ID inválido.');
                $db->query(
                    "UPDATE secretarias SET nome = ?, sigla = ?, tipo_id = ?, ativo = ? WHERE id = ?",
                    [trim($campos['nome']), trim($campos['sigla']), $tipoId, $ativo, $id]
                );
            }
        } else {
            if (empty($campos['nome'])) {
                throw new RuntimeException('O campo Nome é obrigatório.');
            }

            $nome = trim($campos['nome']);

            if ($acao === 'criar') {
                $check = $db->query("SELECT id FROM {$tabela} WHERE nome = ?", [$nome]);
                if (!empty($check['data'])) {
                    $labels = ['regioes' => 'região', 'tipos_locais' => 'tipo de local', 'tipos_equipamento' => 'tipo de equipamento', 'tipos_secretaria' => 'tipo de secretaria', 'marcas' => 'marca', 'status' => 'status'];
                    throw new RuntimeException('Já existe um(a) ' . $labels[$tabela] . ' com este nome.');
                }
                $db->query("INSERT INTO {$tabela} (nome) VALUES (?)", [$nome]);
            } else {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) throw new RuntimeException('ID inválido.');
                $db->query("UPDATE {$tabela} SET nome = ? WHERE id = ?", [$nome, $id]);
            }
        }

        $_SESSION['sucesso'] = 'Registro ' . ($acao === 'criar' ? 'criado' : 'atualizado') . ' com sucesso.';
    } elseif ($acao === 'excluir') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) throw new RuntimeException('ID inválido.');

        if ($tabela === 'secretarias') {
            $dependentes = $db->query("SELECT COUNT(*) as total FROM locais WHERE secretaria_id = ?", [$id]);
            if (!empty($dependentes['data']) && (int)$dependentes['data'][0]->total > 0) {
                throw new RuntimeException('Não é possível excluir: existem locais vinculados a esta secretaria.');
            }
        }

        $db->query("DELETE FROM {$tabela} WHERE id = ?", [$id]);
        $_SESSION['sucesso'] = 'Registro excluído com sucesso.';
    }
} catch (RuntimeException $e) {
    $_SESSION['erro'] = $e->getMessage();
} catch (Throwable $e) {
    logError('Erro ao gerenciar lookup table', ['tabela' => $tabela, 'acao' => $acao, 'error' => $e->getMessage()], 'admin');
    $_SESSION['erro'] = 'Erro ao processar operação.';
}

header('Location: index.php?page=admin_tabelas&tabela=' . urlencode($tabela));
exit;
