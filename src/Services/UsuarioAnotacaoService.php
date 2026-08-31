<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ServiceException;

/**
 * Anotações pessoais privadas do usuário logado.
 *
 * Cada anotação pertence exclusivamente ao usuário (usuario_id). Nenhum outro
 * perfil (inclusive admin/supervisor) tem acesso: todos os métodos filtram por
 * usuario_id da sessão/camada de chamada, mantendo a privacidade absoluta.
 *
 * Modelo de tarefa com ciclo de vida:
 *   pendente -> em_andamento -> realizada  (+ arquivada)
 */
class UsuarioAnotacaoService
{
    public const STATUS = [
        'pendente',
        'em_andamento',
        'realizada',
    ];

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Lista as anotações do usuário (ativas ou arquivadas, por flag).
     */
    public function listar(int $usuarioId, bool $arquivadas = false): array
    {
        $result = $this->db->query(
            "SELECT id, titulo, texto, status, arquivada, created_at, updated_at
             FROM usuario_anotacoes
             WHERE usuario_id = ? AND arquivada = ?
             ORDER BY (status = 'realizada') ASC, updated_at DESC, id DESC",
            [$usuarioId, $arquivadas ? 1 : 0]
        );

        return $result['data'] ?? [];
    }

    /**
     * Busca uma anotação — sempre verifica posse (usuario_id).
     */
    public function get(int $id, int $usuarioId): ?object
    {
        $result = $this->db->query(
            "SELECT id, titulo, texto, status, arquivada, created_at, updated_at
             FROM usuario_anotacoes
             WHERE id = ? AND usuario_id = ?
             LIMIT 1",
            [$id, $usuarioId]
        );

        return $result['data'][0] ?? null;
    }

    /**
     * Cria uma nova anotação para o usuário.
     */
    public function criar(array $payload, int $usuarioId): int
    {
        $titulo = trim((string)($payload['titulo'] ?? ''));
        $texto = trim((string)($payload['texto'] ?? ''));
        $status = $this->normalizarStatus($payload['status'] ?? 'pendente');

        if ($titulo === '') {
            throw ServiceException::validation('Informe o título da anotação.');
        }
        if (mb_strlen($titulo) > 150) {
            throw ServiceException::validation('Título não pode exceder 150 caracteres.');
        }
        if (mb_strlen($texto) > 10000) {
            throw ServiceException::validation('Texto não pode exceder 10000 caracteres.');
        }

        $this->db->query(
            "INSERT INTO usuario_anotacoes (usuario_id, titulo, texto, status)
             VALUES (?, ?, ?, ?)",
            [$usuarioId, $titulo, $texto !== '' ? $texto : null, $status]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza título/texto de uma anotação própria (sem trocar status/arquivada).
     */
    public function atualizar(int $id, array $payload, int $usuarioId): void
    {
        $anotacao = $this->get($id, $usuarioId);
        if ($anotacao === null) {
            throw ServiceException::notFound('Anotação', $id);
        }

        $titulo = trim((string)($payload['titulo'] ?? ''));
        $texto = trim((string)($payload['texto'] ?? ''));

        if ($titulo === '') {
            throw ServiceException::validation('Informe o título da anotação.');
        }
        if (mb_strlen($titulo) > 150) {
            throw ServiceException::validation('Título não pode exceder 150 caracteres.');
        }
        if (mb_strlen($texto) > 10000) {
            throw ServiceException::validation('Texto não pode exceder 10000 caracteres.');
        }

        $this->db->query(
            "UPDATE usuario_anotacoes SET titulo = ?, texto = ? WHERE id = ? AND usuario_id = ?",
            [$titulo, $texto !== '' ? $texto : null, $id, $usuarioId]
        );
    }

    /**
     * Altera o status (pendente/em_andamento/realizada) de uma anotação própria.
     */
    public function mudarStatus(int $id, string $status, int $usuarioId): void
    {
        $anotacao = $this->get($id, $usuarioId);
        if ($anotacao === null) {
            throw ServiceException::notFound('Anotação', $id);
        }

        $status = $this->normalizarStatus($status);

        $this->db->query(
            "UPDATE usuario_anotacoes SET status = ? WHERE id = ? AND usuario_id = ?",
            [$status, $id, $usuarioId]
        );
    }

    /**
     * Arquiva ou desarquiva uma anotação própria.
     */
    public function arquivar(int $id, bool $arquivar, int $usuarioId): void
    {
        $anotacao = $this->get($id, $usuarioId);
        if ($anotacao === null) {
            throw ServiceException::notFound('Anotação', $id);
        }

        $this->db->query(
            "UPDATE usuario_anotacoes SET arquivada = ? WHERE id = ? AND usuario_id = ?",
            [$arquivar ? 1 : 0, $id, $usuarioId]
        );
    }

    /**
     * Exclui definitivamente uma anotação própria.
     */
    public function excluir(int $id, int $usuarioId): void
    {
        $anotacao = $this->get($id, $usuarioId);
        if ($anotacao === null) {
            throw ServiceException::notFound('Anotação', $id);
        }

        $this->db->query(
            "DELETE FROM usuario_anotacoes WHERE id = ? AND usuario_id = ?",
            [$id, $usuarioId]
        );
    }

    private function normalizarStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUS, true)) {
            throw ServiceException::validation('Status inválido.');
        }
        return $status;
    }
}
