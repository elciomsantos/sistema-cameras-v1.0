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
            header('Location: index.php?page=nao_autorizado');
            exit;
        }
        echo 'Acesso nao autorizado a este local.';
        exit;
    }
}
