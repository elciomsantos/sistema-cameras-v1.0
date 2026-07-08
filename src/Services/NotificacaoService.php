<?php

declare(strict_types=1);

class NotificacaoService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    public function gerarPendencias(?int $secretariaId = null): int
    {
        $geradas = 0;

        $pendentes = $this->listarPendenciasManutencao($secretariaId);
        foreach ($pendentes as $pend) {
            if (!$this->jaExiste($pend['tipo'], $pend['entidade_tipo'], $pend['entidade_id'])) {
                $this->criar($pend['tipo'], $pend['titulo'], $pend['mensagem'], $pend['link'], $pend['entidade_tipo'], $pend['entidade_id']);
                $geradas++;
            }
        }

        return $geradas;
    }

    private function listarPendenciasManutencao(?int $secretariaId = null): array
    {
        $pendencias = [];
        $filtroSec = '';
        $paramsSec = [];
        if ($secretariaId !== null) {
            $filtroSec = ' AND e.secretaria_id = ?';
            $paramsSec = [$secretariaId];
        }

        $sqlEquip = "
            SELECT e.id, COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) AS nome,
                   DATEDIFF(NOW(), m.data_hora) AS dias_atraso
            FROM equipamentos e
            JOIN status s ON e.status_id = s.id
            LEFT JOIN locais l ON e.local_id = l.id
            LEFT JOIN (
                SELECT equipamento_id, MIN(data_hora) AS data_hora
                FROM equipamentos_manutencoes
                WHERE os_status_id IN (1, 2)
                GROUP BY equipamento_id
            ) m ON m.equipamento_id = e.id
            WHERE e.deleted_at IS NULL
              AND s.nome = 'MANUTENCAO'
              AND m.data_hora IS NOT NULL
              AND m.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY)
              {$filtroSec}
            ORDER BY m.data_hora ASC
        ";
        $result = $this->db->query($sqlEquip, $paramsSec);
        if (!empty($result['data'])) {
            foreach ($result['data'] as $row) {
                $pendencias[] = [
                    'tipo' => 'manutencao_atrasada',
                    'entidade_tipo' => 'equipamento',
                    'entidade_id' => (int)$row->id,
                    'titulo' => "Manutenção atrasada: {$row->nome}",
                    'mensagem' => "Equipamento {$row->nome} está em manutenção há {$row->dias_atraso} dia(s).",
                    'link' => "?page=manutencao_cameras&busca={$row->id}",
                ];
            }
        }

        $filtroSecAlarme = '';
        $paramsSecAlarme = [];
        if ($secretariaId !== null) {
            $filtroSecAlarme = ' AND l.secretaria_id = ?';
            $paramsSecAlarme = [$secretariaId];
        }

        $sqlAlarm = "
            SELECT ca.id, ca.local, ca.endereco,
                   DATEDIFF(NOW(), am.data_hora) AS dias_atraso
            FROM alarmes_manutencoes am
            JOIN central_alarmes ca ON am.alarme_id = ca.id
            LEFT JOIN locais l ON l.alarme_conta = ca.conta
            WHERE am.os_status_id IN (1, 2)
              AND am.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY)
              {$filtroSecAlarme}
            ORDER BY am.data_hora ASC
        ";
        $result = $this->db->query($sqlAlarm, $paramsSecAlarme);
        if (!empty($result['data'])) {
            foreach ($result['data'] as $row) {
                $nomeAlarme = !empty($row->local) ? $row->local : "Alarme #{$row->id}";
                $pendencias[] = [
                    'tipo' => 'manutencao_atrasada',
                    'entidade_tipo' => 'alarme',
                    'entidade_id' => (int)$row->id,
                    'titulo' => "Manutenção atrasada: {$nomeAlarme}",
                    'mensagem' => "Alarme {$nomeAlarme} ({$row->endereco}) está em manutenção há {$row->dias_atraso} dia(s).",
                    'link' => "?page=manutencao_alarmes&busca={$row->id}",
                ];
            }
        }

        return $pendencias;
    }

    private function jaExiste(string $tipo, string $entidadeTipo, int $entidadeId): bool
    {
        $result = $this->db->query(
            "SELECT id FROM notificacoes WHERE tipo = ? AND entidade_tipo = ? AND entidade_id = ? AND lida = 0 LIMIT 1",
            [$tipo, $entidadeTipo, $entidadeId]
        );
        return !empty($result['data']);
    }

    private function criar(string $tipo, string $titulo, ?string $mensagem, ?string $link, string $entidadeTipo, int $entidadeId): void
    {
        $this->db->query(
            "INSERT INTO notificacoes (tipo, titulo, mensagem, link, entidade_tipo, entidade_id) VALUES (?, ?, ?, ?, ?, ?)",
            [$tipo, $titulo, $mensagem, $link, $entidadeTipo, $entidadeId]
        );
    }

    public function getAll(int $page = 1, int $perPage = 20, string $filtroTipo = '', ?int $secretariaId = null): array
    {
        $perPage = min($perPage, 100);
        $where = [];
        $params = [];

        if ($filtroTipo !== '') {
            $where[] = 'tipo = ?';
            $params[] = $filtroTipo;
        }

        if ($secretariaId !== null) {
            $where[] = 'entidade_id IN (
                SELECT e.id FROM equipamentos e WHERE e.secretaria_id = ?
                UNION
                SELECT ca.id FROM central_alarmes ca LEFT JOIN locais l ON l.alarme_conta = ca.conta WHERE l.secretaria_id = ?
            )';
            $params[] = $secretariaId;
            $params[] = $secretariaId;
        }

        $offset = ($page - 1) * $perPage;
        $whereClause = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

        $countResult = $this->db->query("SELECT COUNT(*) AS total FROM notificacoes {$whereClause}", $params);
        $total = !empty($countResult['data']) ? (int)($countResult['data'][0]->total ?? 0) : 0;

        $result = $this->db->query(
            "SELECT * FROM notificacoes {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $result['data'] ?? [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function getCountNaoLidas(?int $secretariaId = null): int
    {
        if ($secretariaId === null) {
            $result = $this->db->query("SELECT COUNT(*) AS total FROM notificacoes WHERE lida = 0");
            return !empty($result['data']) ? (int)($result['data'][0]->total ?? 0) : 0;
        }

        $result = $this->db->query(
            "SELECT COUNT(*) AS total FROM notificacoes
             WHERE lida = 0
               AND entidade_id IN (
                   SELECT e.id FROM equipamentos e WHERE e.secretaria_id = ?
                   UNION
                   SELECT ca.id FROM central_alarmes ca LEFT JOIN locais l ON l.alarme_conta = ca.conta WHERE l.secretaria_id = ?
               )",
            [$secretariaId, $secretariaId]
        );
        return !empty($result['data']) ? (int)($result['data'][0]->total ?? 0) : 0;
    }

    public function marcarLida(int $id): bool
    {
        $result = $this->db->query("UPDATE notificacoes SET lida = 1 WHERE id = ?", [$id]);
        return $result['status'] === 'success';
    }

    public function marcarTodasLidas(?int $secretariaId = null): bool
    {
        if ($secretariaId === null) {
            $result = $this->db->query("UPDATE notificacoes SET lida = 1 WHERE lida = 0");
            return $result['status'] === 'success';
        }

        $result = $this->db->query(
            "UPDATE notificacoes SET lida = 1
             WHERE lida = 0
               AND entidade_id IN (
                   SELECT e.id FROM equipamentos e WHERE e.secretaria_id = ?
                   UNION
                   SELECT ca.id FROM central_alarmes ca LEFT JOIN locais l ON l.alarme_conta = ca.conta WHERE l.secretaria_id = ?
               )",
            [$secretariaId, $secretariaId]
        );
        return $result['status'] === 'success';
    }
}
