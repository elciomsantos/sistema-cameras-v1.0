<?php

declare(strict_types=1);

class AlarmeService
{
    private $db;

    private const STATUS_PERMITIDOS = ['Ativo', 'Inativo', 'Desligado', 'Manutenção', 'Pendente'];

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    public static function requireAccess(): void
    {
        configureSessionSecurity();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['usuario'])) {
            ApiResponse::unauthorized();
        }
        if (!userHasAccess('supervisor')) {
            ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
        }
    }

    public static function parseJsonInput(): array
    {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $data = is_array($jsonInput) ? $jsonInput : [];
        if (empty($data)) {
            ApiResponse::error('BAD_REQUEST', 'Nenhum dado recebido.');
        }
        return $data;
    }

    public function validateInput(array $data, bool $requireId = false): array
    {
        $rules = [
            'conta' => 'required|numeric|min:1',
            'local' => 'required|string|max:255',
            'status' => 'required|string|max:20',
        ];
        if ($requireId) {
            $rules['id'] = 'required|numeric|min:1';
        }

        $validator = new RequestValidator($data);
        $validator->validate($rules);

        if ($validator->fails()) {
            ApiResponse::validationError($validator->errors());
        }

        $status = trim((string)($data['status'] ?? ''));
        if (!in_array($status, self::STATUS_PERMITIDOS, true)) {
            ApiResponse::error('VALIDATION_ERROR', 'Status inválido. Use: ' . implode(', ', self::STATUS_PERMITIDOS));
        }

        $statusLookup = $this->db->query(
            "SELECT id FROM status WHERE nome = ? LIMIT 1",
            [strtoupper($status)]
        );
        $statusId = ($statusLookup['status'] === 'success' && !empty($statusLookup['data']))
            ? (int)$statusLookup['data'][0]->id
            : null;

        $regiaoText = trim((string)($data['regiao'] ?? '')) ?: null;
        $regiaoId = null;
        if ($regiaoText !== null) {
            $regiaoLookup = $this->db->query(
                "SELECT id FROM regioes WHERE nome = ? LIMIT 1",
                [strtoupper($regiaoText)]
            );
            $regiaoId = ($regiaoLookup['status'] === 'success' && !empty($regiaoLookup['data']))
                ? (int)$regiaoLookup['data'][0]->id
                : null;
        }

        $ip = trim((string)($data['ip'] ?? ''));
        if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
            ApiResponse::error('VALIDATION_ERROR', 'IP inválido.');
        }

        $mac = trim((string)($data['mac'] ?? ''));
        if ($mac !== '' && !preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', $mac)) {
            ApiResponse::error('VALIDATION_ERROR', 'MAC inválido.');
        }

        $conta = max(1, (int)($data['conta'] ?? 0));
        $local = trim((string)($data['local'] ?? ''));
        if ($local === '' || $status === '') {
            ApiResponse::error('VALIDATION_ERROR', 'Local e Status são obrigatórios.');
        }

        return [
            'id' => $requireId ? max(1, (int)($data['id'] ?? 0)) : null,
            'conta' => $conta,
            'local' => $local,
            'status' => $status,
            'status_id' => $statusId,
            'regiao' => $regiaoText,
            'regiao_id' => $regiaoId,
            'endereco' => trim((string)($data['endereco'] ?? '')) ?: null,
            'numero' => trim((string)($data['numero'] ?? '')) ?: null,
            'pgm1' => trim((string)($data['pgm1'] ?? '')) ?: null,
            'pgm2' => trim((string)($data['pgm2'] ?? '')) ?: null,
            'mac' => trim((string)($data['mac'] ?? '')) ?: null,
            'ip' => trim((string)($data['ip'] ?? '')) ?: null,
            'integracao' => trim((string)($data['integracao'] ?? '')) ?: null,
            'camera_gm' => !empty($data['camera_gm']) ? 1 : 0,
            'quant_camera_gm' => max(0, (int)($data['quant_camera_gm'] ?? 0)) ?: null,
            'ip_dvr' => trim((string)($data['ip_dvr'] ?? '')) ?: null,
            'cameras_dvr' => max(0, (int)($data['cameras_dvr'] ?? 0)) ?: null,
            'modelo_alarme_id' => max(0, (int)($data['modelo_alarme_id'] ?? 0)) ?: null,
            'quant_repetidor' => max(0, (int)($data['quant_repetidor'] ?? 0)) ?: null,
            'qtde_sensores' => max(0, (int)($data['qtde_sensores'] ?? 0)) ?: null,
            'documentacao' => trim((string)($data['documentacao'] ?? '')) ?: null,
            'monitorada' => trim((string)($data['monitorada'] ?? '')) ?: null,
            'numero_sei' => trim((string)($data['numero_sei'] ?? '')) ?: null,
            'data_atualizacao' => trim((string)($data['data_atualizacao'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            'observacao' => trim((string)($data['observacao'] ?? '')) ?: null,
        ];
    }

    public function cadastrar(array $params): int
    {
        $this->db->beginTransaction();

        try {
            $sql = "INSERT INTO central_alarmes (
                regiao, regiao_id, conta, status, status_id, local, endereco, numero, pgm1, pgm2, mac, ip,
                integracao, camera_gm, quant_camera_gm, ip_dvr, cameras_dvr, modelo_alarme_id,
                quant_repetidor, qtde_sensores, documentacao, monitorada, numero_sei,
                data_atualizacao, observacao
            ) VALUES (
                :regiao, :regiao_id, :conta, :status, :status_id, :local, :endereco, :numero, :pgm1, :pgm2, :mac, :ip,
                :integracao, :camera_gm, :quant_camera_gm, :ip_dvr, :cameras_dvr, :modelo_alarme_id,
                :quant_repetidor, :qtde_sensores, :documentacao, :monitorada, :numero_sei,
                :data_atualizacao, :observacao
            )";

            $result = $this->db->query($sql, $this->buildParams($params));
            if (!$result || $result['status'] !== 'success') {
                throw new RuntimeException('Falha ao cadastrar alarme.');
            }

            $id = (int)$this->db->lastInsertId();
            $auditAfter = $params;
            $auditAfter['id'] = $id;
            auditEvent($this->db, 'alarmes', $id, 'INSERT', null, $auditAfter, 'api');

            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    public function atualizar(int $id, array $params): void
    {
        $this->db->beginTransaction();

        try {
            $before = null;
            $beforeResult = $this->db->query("SELECT * FROM central_alarmes WHERE id = ?", [$id]);
            if ($beforeResult['status'] === 'success' && !empty($beforeResult['data'])) {
                $before = (array)$beforeResult['data'][0];
            }

            $params['id'] = $id;
            $sql = "UPDATE central_alarmes SET
                regiao = :regiao, regiao_id = :regiao_id,
                conta = :conta, status = :status, status_id = :status_id,
                local = :local, endereco = :endereco, numero = :numero,
                pgm1 = :pgm1, pgm2 = :pgm2, mac = :mac, ip = :ip,
                integracao = :integracao, camera_gm = :camera_gm,
                quant_camera_gm = :quant_camera_gm, ip_dvr = :ip_dvr, cameras_dvr = :cameras_dvr,
                modelo_alarme_id = :modelo_alarme_id, quant_repetidor = :quant_repetidor,
                qtde_sensores = :qtde_sensores, documentacao = :documentacao,
                monitorada = :monitorada, numero_sei = :numero_sei,
                data_atualizacao = :data_atualizacao, observacao = :observacao
             WHERE id = :id";

            $result = $this->db->query($sql, $this->buildParams($params, true));
            if (!$result || $result['status'] !== 'success') {
                throw new RuntimeException('Falha ao atualizar alarme.');
            }

            $auditAfter = $params;
            $auditAfter['id'] = $id;
            auditEvent($this->db, 'alarmes', $id, 'UPDATE', $before, $auditAfter, 'api');

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    private function buildParams(array $data, bool $includeId = false): array
    {
        $params = [
            ':regiao' => $data['regiao'],
            ':regiao_id' => $data['regiao_id'],
            ':conta' => $data['conta'],
            ':status' => $data['status'],
            ':status_id' => $data['status_id'],
            ':local' => $data['local'],
            ':endereco' => $data['endereco'],
            ':numero' => $data['numero'],
            ':pgm1' => $data['pgm1'],
            ':pgm2' => $data['pgm2'],
            ':mac' => $data['mac'],
            ':ip' => $data['ip'],
            ':integracao' => $data['integracao'],
            ':camera_gm' => $data['camera_gm'],
            ':quant_camera_gm' => $data['quant_camera_gm'],
            ':ip_dvr' => $data['ip_dvr'],
            ':cameras_dvr' => $data['cameras_dvr'],
            ':modelo_alarme_id' => $data['modelo_alarme_id'],
            ':quant_repetidor' => $data['quant_repetidor'],
            ':qtde_sensores' => $data['qtde_sensores'],
            ':documentacao' => $data['documentacao'],
            ':monitorada' => $data['monitorada'],
            ':numero_sei' => $data['numero_sei'],
            ':data_atualizacao' => $data['data_atualizacao'],
            ':observacao' => $data['observacao'],
        ];

        if ($includeId && isset($data['id'])) {
            $params[':id'] = $data['id'];
        }

        return $params;
    }
}
