<?php

declare(strict_types=1);

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
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['nome'])) {
            $where[] = 'l.nome LIKE ?';
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
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['nome'])) {
            $where[] = 'l.nome LIKE ?';
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
        WHERE l.id = ? AND l.deleted_at IS NULL";
        $result = $this->db->query($sql, [$id]);
        if ($result['status'] === 'success' && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function checkDuplicado(string $nome, ?int $secretariaId, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM locais WHERE nome = ? AND secretaria_id <=> ? AND deleted_at IS NULL";
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
        $result = $this->db->query("SELECT id, descricao FROM classificacao_enderecos ORDER BY descricao");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getAllRegioes(): array
    {
        $result = $this->db->query("SELECT id, nome FROM regioes ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getAllLocais(): array
    {
        $result = $this->db->query("SELECT id, nome FROM locais WHERE deleted_at IS NULL ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getByTipoNome(string $tipoNome, string $search = '', int $page = 1, int $perPage = 20): array
    {
        $where = 'l.tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = ? LIMIT 1) AND l.deleted_at IS NULL';
        $params = [$tipoNome];

        if ($search !== '') {
            $where .= ' AND (l.nome LIKE ? OR l.logradouro LIKE ? OR l.bairro LIKE ?)';
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
        $where = 'l.tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = ? LIMIT 1) AND l.deleted_at IS NULL';
        $params = [$tipoNome];

        if ($search !== '') {
            $where .= ' AND (l.nome LIKE ? OR l.logradouro LIKE ? OR l.bairro LIKE ?)';
            $searchParam = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql = "SELECT COUNT(*) as total FROM locais l WHERE {$where}";
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? (int)($result['data'][0]->total ?? 0) : 0;
    }

    public function softDelete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID inválido para exclusão.');
        }

        $sql = "UPDATE locais SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL";
        $result = $this->db->query($sql, [$id]);
        return $result['status'] === 'success' && (int)($result['affected_rows'] ?? 0) > 0;
    }

    public function create(array $data): int
    {
        $allowedFields = ['tipo_local_id', 'nome', 'regiao_id', 'secretaria_id',
            'horario_funcionamento', 'telefone', 'ramal', 'email', 'tem_alarme', 'alarme_conta', 'tem_camera', 'cep', 'uf', 'cidade', 'bairro',
            'classificacao_endereco_id', 'logradouro', 'numero', 'descricao_posicao',
            'latitude', 'longitude'];

        if (empty($data['nome'])) {
            throw new InvalidArgumentException('O campo nome é obrigatório.');
        }

        $nome = is_string($data['nome']) ? mb_strtoupper(trim($data['nome'])) : $data['nome'];
        $secretariaId = array_key_exists('secretaria_id', $data)
            ? ($data['secretaria_id'] !== null ? (int)$data['secretaria_id'] : null)
            : null;

        if ($this->checkDuplicado($nome, $secretariaId)) {
            throw new InvalidArgumentException('Já existe um local com este nome nesta secretaria.');
        }

        $data['nome'] = $nome;

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

        // Transacao fecha race condition (duplo submit): checkDuplicado + INSERT na
        // mesma conexao/transaction. Captura erro do trigger trg_locais_bi (SIGNAL
        // SQLSTATE '45000' — duplicidade incl. NULL+NULL) e do indice UNIQUE (23000),
        // traduzindo para InvalidArgumentException com mensagem amigavel.
        //
        // Usa getConnection() quando disponivel (producao) para preservar o SQLSTATE
        // original do PDOException. Se a conexao nao estiver acessivel (mock em
        // tests Unit), quatro via $this->db->query() legacy.
        try {
            $pdo = $this->db->getConnection();
        } catch (\Throwable $connErr) {
            $pdo = null;
        }

        if ($pdo instanceof \PDO) {
            try {
                $this->db->beginTransaction();
                $stmt = $pdo->prepare($sql);
                foreach ($params as $i => $value) {
                    $paramType = \PDO::PARAM_STR;
                    if (is_int($value)) {
                        $paramType = \PDO::PARAM_INT;
                    } elseif (is_null($value)) {
                        $paramType = \PDO::PARAM_NULL;
                    }
                    $stmt->bindValue($i + 1, $value, $paramType);
                }
                $stmt->execute();
                $newId = (int)$pdo->lastInsertId();
                $this->db->commit();
                return $newId;
            } catch (\PDOException $e) {
                if ($this->db->inTransaction()) {
                    try { $this->db->rollback(); } catch (\Throwable $rollbackErr) {}
                }
                if ($this->isDuplicateError($e)) {
                    throw new InvalidArgumentException('Já existe um local com este nome nesta secretaria.');
                }
                logError('LocaisService::create PDOException', ['error' => $e->getMessage(), 'sqlstate' => $e->getCode()], 'locais');
                throw new \RuntimeException('Erro ao cadastrar local.');
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    try { $this->db->rollback(); } catch (\Throwable $rollbackErr) {}
                }
                logError('LocaisService::create erro', ['error' => $e->getMessage()], 'locais');
                throw new \RuntimeException('Erro ao cadastrar local.');
            }
        }

        // Fallback legacy: mock em testes Unit (sem PDO real).
        $result = $this->db->query($sql, $params);
        if ($result['status'] === 'success') {
            return (int)($this->db->lastInsertId() ?? 0);
        }
        return 0;
    }

    /**
     * Detecta erro de violacao de UNIQUE ou de SIGNAL do trigger trg_locais_bi/bu.
     * SQLSTATE '45000' = SIGNAL custom (trigger); '23000' = integrity constraint.
     */
    private function isDuplicateError(\PDOException $e): bool
    {
        $code = (string)$e->getCode();
        $msg = $e->getMessage();
        if ($code === '45000' || $code === '23000') {
            return true;
        }
        // Mensagem do SIGNAL ou de duplicate entry do MySQL (errno 1062)
        return str_contains($msg, 'Ja existe um local')
            || str_contains($msg, 'Duplicate entry')
            || str_contains($msg, '1062');
    }

    public function update(int $id, array $data): bool
    {
        $allowedFields = ['tipo_local_id', 'nome', 'regiao_id', 'secretaria_id',
            'horario_funcionamento', 'telefone', 'ramal', 'email', 'tem_alarme', 'alarme_conta', 'tem_camera', 'cep', 'uf', 'cidade', 'bairro',
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

        // Captura erro do trigger trg_locais_bu (SIGNAL SQLSTATE '45000') ou do
        // indice UNIQUE (23000) — ambos relevantes para duplicidade em UPDATE.
        //
        // Usa getConnection() quando disponivel (producao) para preservar o SQLSTATE
        // original do PDOException. Se a conexao nao estiver acessivel (mock em
        // tests Unit), quatro via $this->db->query() legacy — mesmo padrao do create().
        try {
            $pdo = $this->db->getConnection();
        } catch (\Throwable $connErr) {
            $pdo = null;
        }

        if ($pdo instanceof \PDO) {
            try {
                $stmt = $pdo->prepare($sql);
                foreach ($params as $i => $value) {
                    $paramType = \PDO::PARAM_STR;
                    if (is_int($value)) {
                        $paramType = \PDO::PARAM_INT;
                    } elseif (is_null($value)) {
                        $paramType = \PDO::PARAM_NULL;
                    }
                    $stmt->bindValue($i + 1, $value, $paramType);
                }
                $stmt->execute();
                return true;
            } catch (\PDOException $e) {
                if ($this->isDuplicateError($e)) {
                    throw new InvalidArgumentException('Já existe um local com este nome nesta secretaria.');
                }
                logError('LocaisService::update PDOException', ['error' => $e->getMessage(), 'sqlstate' => $e->getCode()], 'locais');
                throw new \RuntimeException('Erro ao atualizar local.');
            }
        }

        // Fallback legacy: mock em testes Unit (sem PDO real).
        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success';
    }

    private function applyFiltersWhere(array $filters, array &$where, array &$params): void
    {
        $where[] = 'l.deleted_at IS NULL';
        if (!empty($filters['search'])) {
            $where[] = '(l.nome LIKE ? OR l.logradouro LIKE ? OR l.bairro LIKE ? OR l.cidade LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['search']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['nome'])) {
            $where[] = 'l.nome LIKE ?';
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
        if (!empty($filters['tem_alarme'])) {
            if ($filters['tem_alarme'] === 'sim') {
                $where[] = 'l.tem_alarme = 1';
            } elseif ($filters['tem_alarme'] === 'nao') {
                $where[] = 'l.tem_alarme = 0';
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
        ORDER BY l.nome
        LIMIT 10000";

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    /**
     * Streaming generator para export — yielda rows sem carregar tudo em memória.
     *
     * @return \Generator<int, object>
     */
    public function streamLocaisExport(array $filters = []): \Generator
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

        yield from $this->db->queryCursor($sql, $params);
    }
}