<?php

class LocaisService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $perPage = min($perPage, 100);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['nome'])) {
            $where[] = 'UPPER(l.nome) LIKE UPPER(?)';
            $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['nome']) . '%';
        }
        if (!empty($filters['secretaria_id'])) {
            $where[] = 'l.secretaria_id = ?';
            $params[] = (int)$filters['secretaria_id'];
        }
        if (!empty($filters['tipo_local_id'])) {
            $where[] = 'l.tipo_local_id = ?';
            $params[] = (int)$filters['tipo_local_id'];
        }
        if (!empty($filters['regiao_id'])) {
            $where[] = 'l.regiao_id = ?';
            $params[] = (int)$filters['regiao_id'];
        }

        $offset = ($page - 1) * $perPage;
        $whereClause = implode(' AND ', $where);

        $sql = "SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
            COALESCE(eq.total_cameras, 0) as total_cameras,
            COALESCE(eq.total_outros, 0) as total_outros
        FROM locais l
            LEFT JOIN secretarias s ON l.secretaria_id = s.id
            LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
            LEFT JOIN regioes r ON l.regiao_id = r.id
            LEFT JOIN (
                SELECT e.local_id,
                    SUM(CASE WHEN e.tipo_equipamento_id IN (1,2) THEN 1 ELSE 0 END) AS total_cameras,
                    SUM(CASE WHEN e.tipo_equipamento_id IN (3,4) THEN 1 ELSE 0 END) AS total_outros
                FROM equipamentos e WHERE e.deleted_at IS NULL
                GROUP BY e.local_id
            ) eq ON eq.local_id = l.id
        WHERE {$whereClause}
        ORDER BY l.nome
        LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function count(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['nome'])) {
            $where[] = 'UPPER(l.nome) LIKE UPPER(?)';
            $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['nome']) . '%';
        }
        if (!empty($filters['secretaria_id'])) {
            $where[] = 'l.secretaria_id = ?';
            $params[] = (int)$filters['secretaria_id'];
        }
        if (!empty($filters['tipo_local_id'])) {
            $where[] = 'l.tipo_local_id = ?';
            $params[] = (int)$filters['tipo_local_id'];
        }
        if (!empty($filters['regiao_id'])) {
            $where[] = 'l.regiao_id = ?';
            $params[] = (int)$filters['regiao_id'];
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as total FROM locais l WHERE {$whereClause}";
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? (int)($result['data'][0]->total ?? 0) : 0;
    }

    public function getById(int $id): ?object
    {
        $sql = "SELECT l.*, s.sigla, tl.nome as tipo_local_nome
        FROM locais l
            LEFT JOIN secretarias s ON l.secretaria_id = s.id
            LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
        WHERE l.id = ?";
        $result = $this->db->query($sql, [$id]);
        if ($result['status'] === 'success' && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function checkDuplicado(string $nome, ?int $secretariaId, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM locais WHERE UPPER(nome) = UPPER(?) AND secretaria_id <=> ?";
        $params = [$nome, $secretariaId];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' && !empty($result['data']);
    }

    public function getAllTiposLocais(): array
    {
        $result = $this->db->query("SELECT id, nome FROM tipos_locais ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getAllSecretarias(): array
    {
        $result = $this->db->query("SELECT id, nome, sigla FROM secretarias ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getAllClassificacoesEnderecos(): array
    {
        $result = $this->db->query("SELECT id, nome FROM classificacao_enderecos ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getAllRegioes(): array
    {
        $result = $this->db->query("SELECT id, nome FROM regioes ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getAllLocais(): array
    {
        $result = $this->db->query("SELECT id, nome FROM locais ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getByTipoNome(string $tipoNome, string $search = '', int $page = 1, int $perPage = 20): array
    {
        $where = 'l.tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = ? LIMIT 1)';
        $params = [$tipoNome];

        if ($search !== '') {
            $where .= ' AND (UPPER(l.nome) LIKE UPPER(?) OR UPPER(l.logradouro) LIKE UPPER(?) OR UPPER(l.bairro) LIKE UPPER(?))';
            $searchParam = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT l.id, l.nome, l.logradouro, l.bairro, l.cidade, l.uf, l.numero,
            s.nome as secretaria, s.sigla, tl.nome as tipo_local
        FROM locais l
            LEFT JOIN secretarias s ON l.secretaria_id = s.id
            LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
        WHERE {$where}
        ORDER BY l.nome
        LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function countByTipoNome(string $tipoNome, string $search = ''): int
    {
        $where = 'l.tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = ? LIMIT 1)';
        $params = [$tipoNome];

        if ($search !== '') {
            $where .= ' AND (UPPER(l.nome) LIKE UPPER(?) OR UPPER(l.logradouro) LIKE UPPER(?) OR UPPER(l.bairro) LIKE UPPER(?))';
            $searchParam = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql = "SELECT COUNT(*) as total FROM locais l WHERE {$where}";
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? (int)($result['data'][0]->total ?? 0) : 0;
    }

    public function create(array $data): int
    {
        $allowedFields = ['tipo_local_id', 'nome', 'regiao_id', 'secretaria_id',
            'horario_funcionamento', 'telefone', 'email', 'tem_alarme', 'alarme_conta', 'cep', 'uf', 'cidade', 'bairro',
            'classificacao_endereco_id', 'logradouro', 'numero', 'descricao_posicao',
            'latitude', 'longitude'];

        if (empty($data['nome'])) {
            throw new InvalidArgumentException('O campo nome é obrigatório.');
        }

        $fields = [];
        $placeholders = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field;
                $placeholders[] = '?';
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return 0;
        }

        $sql = "INSERT INTO locais (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $result = $this->db->query($sql, $params);

        if ($result['status'] === 'success') {
            return (int)($this->db->lastInsertId() ?? 0);
        }

        return 0;
    }

    public function update(int $id, array $data): bool
    {
        $allowedFields = ['tipo_local_id', 'nome', 'regiao_id', 'secretaria_id',
            'horario_funcionamento', 'telefone', 'email', 'tem_alarme', 'alarme_conta', 'cep', 'uf', 'cidade', 'bairro',
            'classificacao_endereco_id', 'logradouro', 'numero', 'descricao_posicao',
            'latitude', 'longitude'];

        if ($id <= 0) {
            throw new InvalidArgumentException('ID inválido para atualização.');
        }

        $sets = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE locais SET " . implode(', ', $sets) . " WHERE id = ?";
        $result = $this->db->query($sql, $params);

        return $result['status'] === 'success';
    }

    private function applyFiltersWhere(array $filters, array &$where, array &$params): void
    {
        if (!empty($filters['search'])) {
            $where[] = '(UPPER(l.nome) LIKE UPPER(?) OR UPPER(l.logradouro) LIKE UPPER(?) OR UPPER(l.bairro) LIKE UPPER(?) OR UPPER(l.cidade) LIKE UPPER(?))';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['search']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['nome'])) {
            $where[] = 'UPPER(l.nome) LIKE UPPER(?)';
            $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['nome']) . '%';
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
        if (!empty($filters['camera'])) {
            if ($filters['camera'] === 'sim') {
                $where[] = '(SELECT COUNT(*) FROM equipamentos e WHERE e.local_id = l.id AND e.deleted_at IS NULL AND e.tipo_equipamento_id IN (1,2)) > 0';
            } elseif ($filters['camera'] === 'nao') {
                $where[] = '(SELECT COUNT(*) FROM equipamentos e WHERE e.local_id = l.id AND e.deleted_at IS NULL AND e.tipo_equipamento_id IN (1,2)) = 0';
            }
        }
    }

    private function equipCountJoin(): string
    {
        return 'LEFT JOIN (
            SELECT e.local_id,
                SUM(CASE WHEN e.tipo_equipamento_id IN (1,2) THEN 1 ELSE 0 END) AS total_cameras,
                SUM(CASE WHEN e.tipo_equipamento_id IN (3,4) THEN 1 ELSE 0 END) AS total_outros
            FROM equipamentos e WHERE e.deleted_at IS NULL
            GROUP BY e.local_id
        ) eq ON eq.local_id = l.id';
    }

    public function search(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $offset = ($page - 1) * $perPage;
        $whereClause = implode(' AND ', $where);

        $sql = "SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
            COALESCE(eq.total_cameras, 0) as total_cameras,
            COALESCE(eq.total_outros, 0) as total_outros
        FROM locais l
            LEFT JOIN secretarias s ON l.secretaria_id = s.id
            LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
            LEFT JOIN regioes r ON l.regiao_id = r.id
            {$this->equipCountJoin()}
        WHERE {$whereClause}
        ORDER BY l.nome
        LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function countSearch(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as total FROM locais l WHERE {$whereClause}";
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? (int)($result['data'][0]->total ?? 0) : 0;
    }

    public function searchAll(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
            COALESCE(eq.total_cameras, 0) as total_cameras,
            COALESCE(eq.total_outros, 0) as total_outros
        FROM locais l
            LEFT JOIN secretarias s ON l.secretaria_id = s.id
            LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
            LEFT JOIN regioes r ON l.regiao_id = r.id
            {$this->equipCountJoin()}
        WHERE {$whereClause}
        ORDER BY l.nome
        LIMIT 5000";

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getAllLocaisExport(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $this->applyFiltersWhere($filters, $where, $params);

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
            COALESCE(eq.total_cameras, 0) as total_cameras,
            COALESCE(eq.total_outros, 0) as total_outros
        FROM locais l
            LEFT JOIN secretarias s ON l.secretaria_id = s.id
            LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
            LEFT JOIN regioes r ON l.regiao_id = r.id
            {$this->equipCountJoin()}
        WHERE {$whereClause}
        ORDER BY l.nome";

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }
}