<?php

declare(strict_types=1);

use App\Exception\ServiceException;

class AlarmeService
{
    use ParsesInput;

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    private function getStatusPermitidos(): array
    {
        $result = $this->db->query("SELECT nome FROM status ORDER BY nome ASC");
        if ($result['status'] === 'success' && !empty($result['data'])) {
            return array_map(fn($row) => (string)$row->nome, $result['data']);
        }
        return ['Ativo', 'Inativo', 'Desligado', 'Manutenção', 'Pendente'];
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
            throw new ServiceException('VALIDATION_ERROR', implode('; ', $validator->errors()));
        }

        $status = trim((string)($data['status'] ?? ''));
        $permitidos = $this->getStatusPermitidos();
        if (!in_array($status, $permitidos, true)) {
            throw ServiceException::validation('Status invalido. Use: ' . implode(', ', $permitidos));
        }

        $statusLookup = $this->db->query(
            "SELECT id FROM status WHERE UPPER(nome) = UPPER(?) LIMIT 1",
            [$status]
        );
        $statusId = ($statusLookup['status'] === 'success' && !empty($statusLookup['data']))
            ? (int)$statusLookup['data'][0]->id
            : null;

        $regiaoText = trim((string)($data['regiao'] ?? '')) ?: null;
        $regiaoId = null;
        if ($regiaoText !== null) {
            $regiaoLookup = $this->db->query(
                "SELECT id FROM regioes WHERE UPPER(nome) = UPPER(?) LIMIT 1",
                [$regiaoText]
            );
            $regiaoId = ($regiaoLookup['status'] === 'success' && !empty($regiaoLookup['data']))
                ? (int)$regiaoLookup['data'][0]->id
                : null;
        }

        $ip = trim((string)($data['ip'] ?? ''));
        if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
            throw ServiceException::validation('IP invalido.');
        }

        $mac = trim((string)($data['mac'] ?? ''));
        if ($mac !== '' && !preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', $mac)) {
            throw ServiceException::validation('MAC invalido.');
        }

        $conta = max(1, (int)($data['conta'] ?? 0));
        $local = trim((string)($data['local'] ?? ''));
        if ($local === '' || $status === '') {
            throw ServiceException::validation('Local e Status sao obrigatorios.');
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
            'bairro' => trim((string)($data['bairro'] ?? '')) ?: null,
            'cidade' => trim((string)($data['cidade'] ?? '')) ?: null,
            'uf' => trim((string)($data['uf'] ?? '')) ?: null,
            'cep' => trim((string)($data['cep'] ?? '')) ?: null,
            'local_id' => !empty($data['local_id']) ? max(0, (int)($data['local_id'])) : null,
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
        $check = $this->db->query(
            "SELECT id FROM central_alarmes WHERE conta = ? LIMIT 1",
            [$params['conta']]
        );
        if ($check['status'] === 'success' && !empty($check['data'])) {
            throw ServiceException::validation('Ja existe um alarme cadastrado com a conta ' . $params['conta'] . '.');
        }

        $this->db->beginTransaction();

        try {
            enableAuditTriggers($this->db, 'api');

            $sql = "INSERT INTO central_alarmes (
                regiao, regiao_id, conta, status, status_id, local, endereco, numero, bairro, cidade, uf, cep,
                alarme_local_id, pgm1, pgm2, mac, ip,
                integracao, camera_gm, quant_camera_gm, ip_dvr, cameras_dvr, modelo_alarme_id,
                quant_repetidor, qtde_sensores, documentacao, monitorada, numero_sei,
                data_atualizacao, observacao
            ) VALUES (
                :regiao, :regiao_id, :conta, :status, :status_id, :local, :endereco, :numero, :bairro, :cidade, :uf, :cep,
                :local_id, :pgm1, :pgm2, :mac, :ip,
                :integracao, :camera_gm, :quant_camera_gm, :ip_dvr, :cameras_dvr, :modelo_alarme_id,
                :quant_repetidor, :qtde_sensores, :documentacao, :monitorada, :numero_sei,
                :data_atualizacao, :observacao
            )";

            $result = $this->db->query($sql, $this->buildParams($params));
            if (!$result || $result['status'] !== 'success') {
                throw new RuntimeException('Falha ao cadastrar alarme.');
            }

            $id = (int)$this->db->lastInsertId();

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
            enableAuditTriggers($this->db, 'api');

            $params['id'] = $id;
            $sql = "UPDATE central_alarmes SET
                regiao = :regiao, regiao_id = :regiao_id,
                conta = :conta, status = :status, status_id = :status_id,
                local = :local, endereco = :endereco, numero = :numero,
                bairro = :bairro, cidade = :cidade, uf = :uf, cep = :cep,
                alarme_local_id = :local_id,
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

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    public function excluir(int $id): void
    {
        enableAuditTriggers('api');
        $this->db->beginTransaction();

        try {
            $result = $this->db->query(
                "UPDATE central_alarmes SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL",
                [$id]
            );

            if ($result['status'] !== 'success' || empty($result['affected_rows']) || $result['affected_rows'] < 1) {
                throw new RuntimeException('Alarme ja foi excluido ou nao encontrado.');
            }

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
        $optional = ['regiao', 'regiao_id', 'status', 'status_id', 'endereco', 'numero', 'bairro', 'cidade', 'uf', 'cep', 'local_id', 'pgm1', 'pgm2', 'mac', 'ip', 'integracao', 'camera_gm', 'quant_camera_gm', 'ip_dvr', 'cameras_dvr', 'modelo_alarme_id', 'quant_repetidor', 'qtde_sensores', 'documentacao', 'monitorada', 'numero_sei', 'data_atualizacao', 'observacao'];
        $params = [
            ':conta' => $data['conta'],
            ':local' => $data['local'],
        ];
        foreach ($optional as $key) {
            $params[':' . $key] = $data[$key] ?? null;
        }

        if ($includeId && isset($data['id'])) {
            $params[':id'] = $data['id'];
        }

        return $params;
    }
}
