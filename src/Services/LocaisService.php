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
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['nome'])) {
            $where[] = 'UPPER(l.nome) LIKE UPPER(?)';
            $params[] = '%' . $filters['nome'] . '%';
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
            (SELECT COUNT(*) FROM equipamentos e
                WHERE e.local_id = l.id AND e.deleted_at IS NULL
                AND e.tipo_equipamento_id IN (1,2)) as total_cameras,
            (SELECT COUNT(*) FROM equipamentos e
                WHERE e.local_id = l.id AND e.deleted_at IS NULL
                AND e.tipo_equipamento_id IN (3,4)) as total_outros
        FROM locais l
            LEFT JOIN secretarias s ON l.secretaria_id = s.id
            LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
            LEFT JOIN regioes r ON l.regiao_id = r.id
        WHERE {$whereClause}
        ORDER BY l.nome
        LIMIT {$perPage} OFFSET {$offset}";

        $result = $this->db->query($sql, $params);
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function count(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['nome'])) {
            $where[] = 'UPPER(l.nome) LIKE UPPER(?)';
            $params[] = '%' . $filters['nome'] . '%';
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
}