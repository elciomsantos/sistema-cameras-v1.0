<?php
/**
 * Verifica se o usuario tem permissao para acessar a pagina
 * @param string $nivel_requerido Nivel requerido para a pagina
 * @return bool True se tem acesso, false caso contrario
 */
function verificarAcesso($nivel_requerido = 'user') {
    return userHasAccess($nivel_requerido);
}

/**
 * Redireciona para pagina de nao autorizado se nao tiver acesso
 */
function requererAcesso($nivel_requerido = 'user'): void
{
    if (!verificarAcesso($nivel_requerido)) {
        if (!headers_sent()) {
            header('Location: index.php?page=nao_autorizado');
            exit;
        }
        echo 'Acesso nao autorizado.';
        exit;
    }
}

function getUsuarioSecretariaId(): ?int
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!$usuario) return null;

    $secretariaId = $usuario->secretaria_id ?? null;
    return $secretariaId !== null ? (int)$secretariaId : null;
}

function verificarEscopoLocal(int $localId): bool
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!$usuario) return false;

    $nivel = resolveUserAccessLevel($usuario);
    if ($nivel === 'admin') return true;

    $secretariaId = getUsuarioSecretariaId();
    if ($secretariaId === null) return true;

    $db = db();
    $result = $db->query(
        "SELECT secretaria_id FROM locais WHERE id = ?",
        [$localId]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        return false;
    }

    $localSecretariaId = (int)($result['data'][0]->secretaria_id ?? 0);
    return $localSecretariaId === $secretariaId;
}

function requererEscopoLocal(int $localId): void
{
    if (!verificarEscopoLocal($localId)) {
        if (!headers_sent()) {
            header('Location: index.php?page=home');
            exit;
        }
        echo 'Acesso nao autorizado a este local.';
        exit;
    }
}

function verificarEscopoEquipamento(int $equipamentoId): bool
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!$usuario) return false;

    if (in_array(resolveUserAccessLevel($usuario), ['admin', 'supervisor'], true)) return true;

    $secretariaId = getUsuarioSecretariaId();
    if ($secretariaId === null) return true;

    $db = db();
    $result = $db->query(
        "SELECT secretaria_id FROM equipamentos WHERE id = ? AND deleted_at IS NULL",
        [$equipamentoId]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        return false;
    }

    $equipSecretariaId = (int)($result['data'][0]->secretaria_id ?? 0);
    return $equipSecretariaId === $secretariaId;
}

function getAnexoLocalId(int $anexoId): ?int
{
    $db = db();
    $result = $db->query(
        "SELECT
            a.equipamento_id,
            a.alarme_id,
            a.manutencao_camera_id,
            a.manutencao_alarme_id,
            COALESCE(
                (SELECT local_id FROM equipamentos WHERE id = a.equipamento_id AND deleted_at IS NULL),
                (SELECT local_id FROM central_alarmes WHERE id = a.alarme_id),
                (SELECT e.local_id FROM equipamentos_manutencoes em
                 JOIN equipamentos e ON e.id = em.equipamento_id
                 WHERE em.id = a.manutencao_camera_id AND e.deleted_at IS NULL),
                (SELECT ca.local_id FROM alarmes_manutencoes am
                 JOIN central_alarmes ca ON ca.id = am.alarme_id
                 WHERE am.id = a.manutencao_alarme_id)
            ) AS local_id
        FROM equipamentos_anexos a
        WHERE a.id = ?",
        [$anexoId]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        return null;
    }

    $localId = $result['data'][0]->local_id ?? null;
    return $localId !== null ? (int)$localId : null;
}

function verificarEscopoAnexo(int $anexoId): bool
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!$usuario) return false;

    if (in_array(resolveUserAccessLevel($usuario), ['admin', 'supervisor'], true)) return true;

    $secretariaId = getUsuarioSecretariaId();
    if ($secretariaId === null) return true;

    $localId = getAnexoLocalId($anexoId);
    if ($localId === null) return false;

    return verificarEscopoLocal($localId);
}

function verificarEscopoAlarme(int $alarmeId): bool
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!$usuario) return false;

    if (in_array(resolveUserAccessLevel($usuario), ['admin', 'supervisor'], true)) return true;

    $secretariaId = getUsuarioSecretariaId();
    if ($secretariaId === null) return true;

    $db = db();
    $result = $db->query(
        "SELECT l.secretaria_id
         FROM central_alarmes ca
         LEFT JOIN locais l ON l.alarme_conta = ca.conta
         WHERE ca.id = ? LIMIT 1",
        [$alarmeId]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        return false;
    }

    $alarmeSecretariaId = (int)($result['data'][0]->secretaria_id ?? 0);
    return $alarmeSecretariaId > 0 && $alarmeSecretariaId === $secretariaId;
}
