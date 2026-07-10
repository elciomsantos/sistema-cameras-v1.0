<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\DatabaseInterface;

/**
 * Repository para acesso a dados de locais.
 * Encapsula todas as queries SQL da tabela locais e tabelas relacionadas.
 */
class LocaisRepository
{
    public function __construct(
        private DatabaseInterface $db,
    ) {}

    private const ALLOWED_FIELDS = [
        'tipo_local_id', 'nome', 'regiao_id', 'secretaria_id',
        'horario_funcionamento', 'telefone', 'ramal', 'email',
        'tem_alarme', 'alarme_conta', 'tem_camera',
        'cep', 'uf', 'cidade', 'bairro',
        'classificacao_endereco_id', 'logradouro', 'numero',
        'descricao_posicao', 'latitude', 'longitude',
    ];

    private const EQUIP_COUNT_JOIN = <<<'SQL'
        LEFT JOIN (
            SELECT e.local_id,
                SUM(CASE WHEN e.tipo_equipamento_id IN (1,2) THEN 1 ELSE 0 END) AS total_cameras,
                SUM(CASE WHEN e.tipo_equipamento_id IN (3,4) THEN 1 ELSE 0 END) AS total_outros
            FROM equipamentos e WHERE e.deleted_at IS NULL
            GROUP BY e.local_id
        ) eq ON eq.local_id = l.id
    SQL;

    public function findAll(array $filters, int $page, int $perPage): array
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
                COALESCE(eq.total_cameras, 0) as total_cameras,
                COALESCE(eq.total_outros, 0) as total_outros
            FROM locais l
                LEFT JOIN secretarias s ON l.secretaria_id = s.id
                LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
                LEFT JOIN regioes r ON l.regiao_id = r.id
                " . self::EQUIP_COUNT_JOIN . "
            WHERE {$whereClause}
            ORDER BY l.nome
            LIMIT ? OFFSET ?
        ";
        $params[] = $perPage;
        $params[] = $offset;

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function countAll(array $filters): int
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);

        $result = $this->db->query(
            "SELECT COUNT(*) as total FROM locais l WHERE {$whereClause}",
            $params
        );

        return ($result['status'] === 'success' && !empty($result['data']))
            ? (int)($result['data'][0]->total ?? 0)
            : 0;
    }

    public function findById(int $id): ?object
    {
        $result = $this->db->query(
            "SELECT l.*, s.sigla, tl.nome as tipo_local_nome
             FROM locais l
                 LEFT JOIN secretarias s ON l.secretaria_id = s.id
                 LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
             WHERE l.id = ? AND l.deleted_at IS NULL",
            [$id]
        );

        return ($result['status'] === 'success' && !empty($result['data']))
            ? $result['data'][0]
            : null;
    }

    public function existsDuplicate(string $nome, ?int $secretariaId, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM locais WHERE UPPER(nome) = UPPER(?) AND secretaria_id <=> ? AND deleted_at IS NULL";
        $params = [$nome, $secretariaId];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' && !empty($result['data']);
    }

    public function findTiposLocais(): array
    {
        $result = $this->db->query("SELECT id, nome FROM tipos_locais ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function findSecretarias(): array
    {
        $result = $this->db->query("SELECT id, nome, sigla FROM secretarias ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function findClassificacoesEnderecos(): array
    {
        $result = $this->db->query("SELECT id, descricao FROM classificacao_enderecos ORDER BY descricao");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function findRegioes(): array
    {
        $result = $this->db->query("SELECT id, nome FROM regioes ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function findAllBasic(): array
    {
        $result = $this->db->query("SELECT id, nome FROM locais WHERE deleted_at IS NULL ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function findByTipoNome(string $tipoNome, string $search, int $page, int $perPage): array
    {
        $where = [
            "l.tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = ? LIMIT 1)",
            'l.deleted_at IS NULL',
        ];
        $params = [$tipoNome];

        if ($search !== '') {
            $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $search);
            $like = '%' . $likeSafe . '%';
            $where[] = '(UPPER(l.nome) LIKE UPPER(?) OR UPPER(l.logradouro) LIKE UPPER(?) OR UPPER(l.bairro) LIKE UPPER(?))';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT l.id, l.nome, l.logradouro, l.bairro, l.cidade, l.uf, l.numero,
                s.nome as secretaria, s.sigla, tl.nome as tipo_local
            FROM locais l
                LEFT JOIN secretarias s ON l.secretaria_id = s.id
                LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
            WHERE {$whereClause}
            ORDER BY l.nome
            LIMIT ? OFFSET ?
        ";
        $params[] = $perPage;
        $params[] = $offset;

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function countByTipoNome(string $tipoNome, string $search): int
    {
        $where = [
            "l.tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = ? LIMIT 1)",
            'l.deleted_at IS NULL',
        ];
        $params = [$tipoNome];

        if ($search !== '') {
            $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $search);
            $like = '%' . $likeSafe . '%';
            $where[] = '(UPPER(l.nome) LIKE UPPER(?) OR UPPER(l.logradouro) LIKE UPPER(?) OR UPPER(l.bairro) LIKE UPPER(?))';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereClause = implode(' AND ', $where);
        $result = $this->db->query(
            "SELECT COUNT(*) as total FROM locais l WHERE {$whereClause}",
            $params
        );

        return ($result['status'] === 'success' && !empty($result['data']))
            ? (int)($result['data'][0]->total ?? 0)
            : 0;
    }

    public function softDelete(int $id): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID invalido para exclusao.');
        }

        $result = $this->db->query(
            "UPDATE locais SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );

        return $result['status'] === 'success' && ($result['affected_rows'] ?? 0) > 0;
    }

    public function create(array $data): int
    {
        $nome = trim((string)($data['nome'] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Nome do local e obrigatorio.');
        }

        $validFields = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));
        if (empty($validFields)) {
            return 0;
        }

        $columns = implode(', ', array_keys($validFields));
        $placeholders = implode(', ', array_fill(0, count($validFields), '?'));

        $result = $this->db->query(
            "INSERT INTO locaiS ({$columns}) VALUES ({$placeholders})",
            array_values($validFields)
        );

        return $result['status'] === 'success' ? (int)$this->db->lastInsertId() : 0;
    }

    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID invalido para atualizacao.');
        }

        $validFields = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));
        if (empty($validFields)) {
            return false;
        }

        $setParts = [];
        $params = [];
        foreach ($validFields as $key => $value) {
            $setParts[] = "{$key} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $setClause = implode(', ', $setParts);

        $result = $this->db->query(
            "UPDATE locais SET {$setClause} WHERE id = ?",
            $params
        );

        return $result['status'] === 'success';
    }

    /**
     * Search com filtros avançados (usado pela API de busca).
     */
    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
                COALESCE(eq.total_cameras, 0) as total_cameras,
                COALESCE(eq.total_outros, 0) as total_outros
            FROM locais l
                LEFT JOIN secretarias s ON l.secretaria_id = s.id
                LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
                LEFT JOIN regioes r ON l.regiao_id = r.id
                " . self::EQUIP_COUNT_JOIN . "
            WHERE {$whereClause}
            ORDER BY l.nome
            LIMIT ? OFFSET ?
        ";
        $params[] = $perPage;
        $params[] = $offset;

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function countSearch(array $filters): int
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);

        $result = $this->db->query(
            "SELECT COUNT(*) as total FROM locais l WHERE {$whereClause}",
            $params
        );

        return ($result['status'] === 'success' && !empty($result['data']))
            ? (int)($result['data'][0]->total ?? 0)
            : 0;
    }

    public function searchAll(array $filters): array
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
                COALESCE(eq.total_cameras, 0) as total_cameras,
                COALESCE(eq.total_outros, 0) as total_outros
            FROM locais l
                LEFT JOIN secretarias s ON l.secretaria_id = s.id
                LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
                LEFT JOIN regioes r ON l.regiao_id = r.id
                " . self::EQUIP_COUNT_JOIN . "
            WHERE {$whereClause}
            ORDER BY l.nome
            LIMIT 5000
        ";

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function findAllForExport(array $filters): array
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
                COALESCE(eq.total_cameras, 0) as total_cameras,
                COALESCE(eq.total_outros, 0) as total_outros
            FROM locais l
                LEFT JOIN secretarias s ON l.secretaria_id = s.id
                LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
                LEFT JOIN regioes r ON l.regiao_id = r.id
                " . self::EQUIP_COUNT_JOIN . "
            WHERE {$whereClause}
            ORDER BY l.nome
            LIMIT 10000
        ";

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    /**
     * @return \Generator<int, object>
     */
    public function streamExport(array $filters): \Generator
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
                COALESCE(eq.total_cameras, 0) as total_cameras,
                COALESCE(eq.total_outros, 0) as total_outros
            FROM locais l
                LEFT JOIN secretarias s ON l.secretaria_id = s.id
                LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
                LEFT JOIN regioes r ON l.regiao_id = r.id
                " . self::EQUIP_COUNT_JOIN . "
            WHERE {$whereClause}
            ORDER BY l.nome
        ";

        yield from $this->db->queryCursor($sql, $params);
    }

    private function applyFiltersWhere(array $filters, array &$where, array &$params): void
    {
        if (!empty($filters['search'])) {
            $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $filters['search']);
            $like = '%' . $likeSafe . '%';
            $where[] = '(UPPER(l.nome) LIKE UPPER(?) OR UPPER(l.logradouro) LIKE UPPER(?) OR UPPER(l.bairro) LIKE UPPER(?) OR UPPER(l.cidade) LIKE UPPER(?))';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['nome'])) {
            $likeSafe = str_replace(['%', '_'], ['\%', '\_'], $filters['nome']);
            $where[] = 'UPPER(l.nome) LIKE UPPER(?)';
            $params[] = '%' . $likeSafe . '%';
        }

        if (!empty($filters['tipo_local_id'])) {
            $where[] = 'l.tipo_local_id = ?';
            $params[] = (int)$filters['tipo_local_id'];
        }

        if (!empty($filters['secretaria_id'])) {
            $where[] = 'l.secretaria_id = ?';
            $params[] = (int)$filters['secretaria_id'];
        }

        if (!empty($filters['regiao_id'])) {
            $where[] = 'l.regiao_id = ?';
            $params[] = (int)$filters['regiao_id'];
        }

        if (isset($filters['camera']) && $filters['camera'] !== '') {
            if ($filters['camera'] === '1') {
                $where[] = "(SELECT COUNT(*) FROM equipamentos e WHERE e.local_id = l.id AND e.deleted_at IS NULL) > 0";
            } else {
                $where[] = "(SELECT COUNT(*) FROM equipamentos e WHERE e.local_id = l.id AND e.deleted_at IS NULL) = 0";
            }
        }

        if (isset($filters['tem_alarme']) && $filters['tem_alarme'] !== '') {
            $where[] = 'l.tem_alarme = ?';
            $params[] = (int)$filters['tem_alarme'];
        }
    }
}
