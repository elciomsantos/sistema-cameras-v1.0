<?php

declare(strict_types=1);

use App\Exception\ServiceException;

/**
 * Serviço único de manutenção de câmeras e alarmes (R-001).
 *
 * Elimina a duplicação entre api/v2/api_manutencao_cameras.php e
 * api/v2/api_manutencao_alarmes.php: o fluxo de listagem e de ordens de
 * serviço (create/start/finalize) é único e parametrizado por uma
 * configuração declarativa de entidade definida nos endpoints.
 */
class ManutencaoService
{
    private \database $db;
    private array $cfg;

    public function __construct(?\database $db = null, array $config = [])
    {
        $this->db = $db ?? db();
        $this->cfg = $config;
    }

    /**
     * Monta o payload de listagem (entidades, opções e ordens de serviço).
     */
    public function loadPayload(array $opts): array
    {
        $cfg = $this->cfg;
        $idParam = (string)($cfg['entity_id_param'] ?? 'equipamento_id');

        $entidadeId = max(0, (int)($opts[$idParam] ?? $opts['equipamento_id'] ?? 0));
        $page = max(1, (int)($opts['page'] ?? 1));
        $perPage = max(10, (int)($opts['per_page'] ?? 20));
        $busca = trim((string)($opts['busca'] ?? ''));
        $sortBy = trim((string)($opts['sort_by'] ?? 'data_hora'));
        $sortDir = trim((string)($opts['sort_dir'] ?? 'DESC'));
        $includeLists = (bool)($opts['include_lists'] ?? true);
        $dataInicial = trim((string)($opts['data_inicial'] ?? ''));
        $dataFinal = trim((string)($opts['data_final'] ?? ''));

        $lista = [];
        $statusOptions = [];
        $procedimentoOptions = [];
        $defaults = getMaintenanceDefaultIds($this->db);

        if ($includeLists) {
            $lista = $this->loadEntityList();
            $statusOptions = $this->loadCachedOptions(
                'manut_status_options',
                'SELECT id, nome FROM status ORDER BY nome ASC'
            );
            $procedimentoOptions = $this->loadCachedOptions(
                'manut_procedimento_options',
                'SELECT id, nome FROM procedimentos ORDER BY nome ASC'
            );
        }

        $whereParts = [];
        $params = [];
        if ($entidadeId > 0) {
            $whereParts[] = 'm.' . $cfg['entity_column'] . ' = :entidade_id';
            $params[':entidade_id'] = $entidadeId;
        }
        if ($busca !== '') {
            $builder = $cfg['search_builder'] ?? null;
            if (is_callable($builder)) {
                [$whereParts, $params] = $this->applyBuilder($builder, $whereParts, $params, $busca);
            }
        }
        $whereSql = empty($whereParts) ? '' : 'AND ' . implode(' AND ', $whereParts);

        $historicoParts = $whereParts;
        $historicoParams = $params;
        if ($busca !== '') {
            $historyBuilder = $cfg['history_search_builder'] ?? null;
            if (is_callable($historyBuilder)) {
                [$historicoParts, $historicoParams] = $this->applyBuilder($historyBuilder, $historicoParts, $historicoParams, $busca);
            }
        }
        if ($dataInicial !== '') {
            if (!\DateTime::createFromFormat('Y-m-d', $dataInicial)) {
                $dataInicial = '';
            } else {
                $historicoParts[] = 'COALESCE(m.data_execucao, m.data_hora, m.created_at) >= :data_inicial';
                $historicoParams[':data_inicial'] = $dataInicial . ' 00:00:00';
            }
        }
        if ($dataFinal !== '') {
            if (!\DateTime::createFromFormat('Y-m-d', $dataFinal)) {
                $dataFinal = '';
            } else {
                $historicoParts[] = 'COALESCE(m.data_execucao, m.data_hora, m.created_at) <= :data_final';
                $historicoParams[':data_final'] = $dataFinal . ' 23:59:59';
            }
        }
        $historicoWhereSql = empty($historicoParts) ? '' : 'AND ' . implode(' AND ', $historicoParts);

        $pendingOrders = $this->runList(
            str_replace('{{where}}', $whereSql, (string)($cfg['pending_sql'] ?? '')),
            $params
        );
        $executingOrders = $this->runList(
            str_replace('{{where}}', $whereSql, (string)($cfg['executing_sql'] ?? '')),
            $params
        );

        $countSql = str_replace('{{historico_where}}', $historicoWhereSql, (string)($cfg['count_sql'] ?? ''));
        $countResult = $this->db->query($countSql, $historicoParams);
        $total = 0;
        if ($countResult['status'] === 'success' && !empty($countResult['data'])) {
            $total = (int)($countResult['data'][0]->total ?? 0);
        }

        $offset = ($page - 1) * $perPage;
        $sortMap = is_array($cfg['sort_map'] ?? null) ? $cfg['sort_map'] : [];
        $sortColumn = $sortMap[$sortBy] ?? 'COALESCE(m.data_execucao, m.data_hora, m.created_at)';
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $orderSql = 'ORDER BY ' . $sortColumn . ' ' . $direction . ', m.id DESC';

        $historySql = strtr((string)($cfg['history_sql'] ?? ''), [
            '{{historico_where}}' => $historicoWhereSql,
            '{{order}}' => $orderSql,
        ]);
        $historyParams = [...$historicoParams, $perPage, $offset];
        $historico = $this->runList($historySql, $historyParams);

        return [
            (string)($cfg['list_payload_key'] ?? 'itens') => $lista,
            'status_options' => $statusOptions,
            'procedimento_options' => $procedimentoOptions,
            'defaults' => $defaults,
            'pending_orders' => $pendingOrders,
            'executing_orders' => $executingOrders,
            'historico' => $historico,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            ],
            'sorting' => [
                'sort_by' => $sortBy,
                'sort_dir' => $direction,
            ],
        ];
    }

    /**
     * Cadastra uma ordem de serviço em estado pendente.
     */
    public function createOs(array $payload, int $usuarioId): void
    {
        $cfg = $this->cfg;

        $rules = is_array($cfg['create_rules'] ?? null) ? $cfg['create_rules'] : [];
        $validator = new RequestValidator($payload);
        $validator->validate($rules);
        if ($validator->fails()) {
            throw new ServiceException('VALIDATION_ERROR', $this->formatValidationErrors($validator->errors()));
        }

        $entityId = $this->entityId($payload);
        $problemas = trim((string)($payload['problemas'] ?? ''));
        $dataHora = $this->parseDataHora($payload);
        $numeroOs = trim((string)($payload['numero_os'] ?? '')) ?: null;

        if (strlen($problemas) < 5) {
            throw ServiceException::validation('Descreva os problemas da ordem de serviço.');
        }
        if (strlen($problemas) > 1000) {
            throw ServiceException::validation('Problemas não pode exceder 1000 caracteres.');
        }
        if ((bool)($cfg['validate_numero_os'] ?? false) && $numeroOs !== null
            && (strlen($numeroOs) > 50 || preg_match('/^[A-Za-z0-9\-_\/]+$/', $numeroOs) !== 1)) {
            throw ServiceException::validation('Número da OS inválido. Use apenas letras, números, -, _ ou / (máx. 50 chars).');
        }

        $entityResult = $this->db->query((string)($cfg['create_entity_lookup'] ?? ''), [':id' => $entityId]);
        if ($entityResult['status'] !== 'success' || empty($entityResult['data'])) {
            throw $this->notFoundException((string)($cfg['entity_resource'] ?? ''), $entityId);
        }
        $this->verifyScope($entityId);
        $entityData = (array)$entityResult['data'][0];

        $this->audit();

        $createInsert = $cfg['create_insert'] ?? null;
        if (!is_callable($createInsert)) {
            throw ServiceException::internal('Configuração de inserção ausente.');
        }
        [$insertSql, $insertParams] = $this->applyCreateInsert($createInsert, $payload, $entityData, $dataHora, $usuarioId, $entityId);
        $insert = $this->db->query($insertSql, $insertParams);
        if ($insert['status'] !== 'success') {
            throw ServiceException::internal('Falha ao criar ordem de serviço.');
        }

        $this->invalidateEntityListCache();
    }

    /**
     * Inicia a execução de uma ordem de serviço cadastrada.
     */
    public function startOs(array $payload, int $usuarioId): void
    {
        $cfg = $this->cfg;
        $osId = max(1, (int)($payload['os_id'] ?? 0));
        $entityId = $this->entityId($payload);

        if (!$osId || !$entityId) {
            throw ServiceException::validation('ID da OS e ' . (string)($cfg['entity_title'] ?? '') . ' são obrigatórios.');
        }

        $this->verifyScope($entityId);

        $osResult = $this->db->query($this->buildOsLookupSql(), [':id' => $osId]);
        if ($osResult['status'] !== 'success' || empty($osResult['data'])) {
            throw $this->notFoundException('ordem de serviço', $osId);
        }
        $osRecord = (array)$osResult['data'][0];
        if ((int)($osRecord[(string)($cfg['entity_column'] ?? '')] ?? 0) !== $entityId) {
            throw ServiceException::validation((string)($cfg['mismatch_message'] ?? ''));
        }
        if (($osRecord['os_status_id'] ?? 0) !== 1) {
            throw ServiceException::validation('A ordem de serviço precisa estar cadastrada para iniciar execução.');
        }

        $this->audit();

        $update = $this->db->query($this->buildStartUpdateSql(), [':id' => $osId]);
        if ($update['status'] !== 'success') {
            throw ServiceException::internal('Falha ao iniciar execução da ordem de serviço.');
        }

        $this->invalidateEntityListCache();
    }

    /**
     * Finaliza uma ordem de serviço em execução.
     */
    public function finalizeOs(array $payload, int $usuarioId): void
    {
        $cfg = $this->cfg;
        $osId = max(1, (int)($payload['os_id'] ?? 0));
        $entityId = $this->entityId($payload);
        $descricao = trim((string)($payload['descricao'] ?? ''));
        $dataHora = $this->parseDataHora($payload);
        $procedimentoId = max(0, (int)($payload['procedimento_id'] ?? 0)) ?: null;
        $statusId = max(0, (int)($payload['status_id'] ?? 0)) ?: null;
        $numeroOs = trim((string)($payload['numero_os'] ?? '')) ?: null;
        $tecnico = trim((string)($payload['tecnico'] ?? '')) ?: null;
        $localServico = trim((string)($payload['local_servico'] ?? '')) ?: null;
        $enderecoServico = trim((string)($payload['endereco_servico'] ?? '')) ?: null;
        $pecasPrevistas = trim((string)($payload['pecas_previstas'] ?? '')) ?: null;

        if (strlen($descricao) < 5) {
            throw ServiceException::validation('Descrição deve ter ao menos 5 caracteres.');
        }
        if (strlen($descricao) > 2000) {
            throw ServiceException::validation('Descrição não pode exceder 2000 caracteres.');
        }
        if ($tecnico !== null && strlen($tecnico) > 100) {
            throw ServiceException::validation('Técnico não pode exceder 100 caracteres.');
        }
        if ($localServico !== null && strlen($localServico) > 255) {
            throw ServiceException::validation('Local de serviço não pode exceder 255 caracteres.');
        }
        if ($enderecoServico !== null && strlen($enderecoServico) > 255) {
            throw ServiceException::validation('Endereço de serviço não pode exceder 255 caracteres.');
        }
        if ($pecasPrevistas !== null && strlen($pecasPrevistas) > 255) {
            throw ServiceException::validation('Peças previstas não pode exceder 255 caracteres.');
        }

        $this->verifyScope($entityId);

        $osResult = $this->db->query($this->buildOsLookupSql(), [':id' => $osId]);
        if ($osResult['status'] !== 'success' || empty($osResult['data'])) {
            throw $this->notFoundException('ordem de serviço', $osId);
        }
        $osRecord = (array)$osResult['data'][0];
        if ((int)($osRecord[(string)($cfg['entity_column'] ?? '')] ?? 0) !== $entityId) {
            throw ServiceException::validation((string)($cfg['mismatch_message'] ?? ''));
        }
        if (($osRecord['os_status_id'] ?? 0) !== 2) {
            throw ServiceException::validation('A ordem de serviço precisa estar em execução antes de finalizar.');
        }

        $this->audit();
        $this->db->beginTransaction();

        $update = $this->db->query($this->buildFinalizeUpdateSql(), [
            ':procedimento_id' => $procedimentoId,
            ':status_id' => $statusId,
            ':numero_os' => $numeroOs,
            ':tecnico' => $tecnico,
            ':local_servico' => $localServico,
            ':endereco_servico' => $enderecoServico,
            ':descricao' => $descricao,
            ':pecas_previstas' => $pecasPrevistas,
            ':data_execucao' => $dataHora,
            ':executado_por' => $usuarioId,
            ':id' => $osId,
        ]);
        if ($update['status'] !== 'success') {
            throw new RuntimeException('Falha ao finalizar a ordem de serviço.');
        }

        $finalizeExtra = $cfg['finalize_extra'] ?? null;
        if (is_callable($finalizeExtra)) {
            $finalizeExtra($this->db, $entityId, $osId, $statusId, $usuarioId, $descricao);
        }

        $this->db->commit();
        $this->invalidateEntityListCache();
    }

    private function entityId(array $input): int
    {
        $param = (string)($this->cfg['entity_id_param'] ?? 'equipamento_id');
        return max(0, (int)($input[$param] ?? $input['equipamento_id'] ?? 0));
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function applyBuilder(callable $builder, array $parts, array $params, string $busca): array
    {
        $result = $builder($parts, $params, $busca);
        if (!is_array($result) || !isset($result[0], $result[1])
            || !is_array($result[0]) || !is_array($result[1])) {
            return [$parts, $params];
        }
        return [$result[0], $result[1]];
    }

    /**
     * @return array{0: string, 1: array}
     */
    private function applyCreateInsert(
        callable $insert,
        array $payload,
        array $entityData,
        string $dataHora,
        int $usuarioId,
        int $entityId
    ): array {
        $result = $insert($payload, $entityData, $dataHora, $usuarioId, $entityId);
        if (!is_array($result) || !isset($result[0], $result[1])
            || !is_string($result[0]) || !is_array($result[1])) {
            throw ServiceException::internal('Configuração de inserção inválida.');
        }
        return [$result[0], $result[1]];
    }

    private function verifyScope(int $entityId): void
    {
        $verifier = $this->cfg['scope_verifier'] ?? null;
        if (is_callable($verifier) && !$verifier($entityId)) {
            throw ServiceException::forbidden((string)($this->cfg['scope_message'] ?? 'Acesso negado.'));
        }
    }

    private function audit(): void
    {
        if ((bool)($this->cfg['audit'] ?? false)) {
            enableAuditTriggers($this->db, 'api');
        }
    }

    private function notFoundException(string $resource, int $id): ServiceException
    {
        return new ServiceException('NOT_FOUND', "Recurso '{$resource}' com ID {$id} não encontrado");
    }

    private function parseDataHora(array $payload): string
    {
        $value = trim((string)($payload['data_hora'] ?? ''));
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $value)
            ?: \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if (!$dt) {
            throw ServiceException::validation('Formato de data/hora inválido. Use YYYY-MM-DDTHH:MM ou YYYY-MM-DD HH:MM:SS.');
        }
        return $dt->format('Y-m-d H:i:s');
    }

    private function invalidateEntityListCache(): void
    {
        $cacheKey = $this->listCacheKey();
        if ($cacheKey !== null && function_exists('apcu_delete')) {
            apcu_delete($cacheKey);
        }
    }

    private function listCacheKey(): ?string
    {
        $key = $this->cfg['list_cache_key'] ?? null;
        return is_string($key) && $key !== '' ? $key : null;
    }

    private function loadEntityList(): array
    {
        $sql = (string)($this->cfg['list_sql'] ?? '');
        if ($sql === '') {
            return [];
        }

        $cacheKey = $this->listCacheKey();
        if ($cacheKey !== null && function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $result = $this->db->query($sql);
        $rows = $result['data'] ?? [];
        $lista = ($result['status'] ?? '') === 'success' && is_array($rows)
            ? array_map(fn($row) => (array)$row, $rows)
            : [];

        if ($cacheKey !== null && function_exists('apcu_store') && !empty($lista)) {
            apcu_store($cacheKey, $lista, 300);
        }

        return $lista;
    }

    private function loadCachedOptions(string $cacheKey, string $sql): array
    {
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $result = $this->db->query($sql);
        $rows = $result['data'] ?? [];
        $options = ($result['status'] ?? '') === 'success' && is_array($rows)
            ? array_map(fn($row) => (array)$row, $rows)
            : [];

        if (function_exists('apcu_store') && !empty($options)) {
            apcu_store($cacheKey, $options, 300);
        }

        return $options;
    }

    private function runList(string $sql, array $params = []): array
    {
        $result = $this->db->query($sql, $params);
        $rows = $result['data'] ?? [];
        return ($result['status'] ?? '') === 'success' && is_array($rows)
            ? array_map(fn($row) => (array)$row, $rows)
            : [];
    }

    private function buildOsLookupSql(): string
    {
        return 'SELECT id, ' . $this->cfg['entity_column'] . ', os_status_id FROM '
            . $this->cfg['table'] . ' WHERE id = :id LIMIT 1';
    }

    private function buildStartUpdateSql(): string
    {
        return 'UPDATE ' . $this->cfg['table']
            . ' SET os_status_id = 2, updated_at = CURRENT_TIMESTAMP WHERE id = :id';
    }

    private function buildFinalizeUpdateSql(): string
    {
        return 'UPDATE ' . $this->cfg['table'] . ' SET
            procedimento_id = :procedimento_id,
            status_id = :status_id,
            numero_os = :numero_os,
            tecnico = :tecnico,
            local_servico = :local_servico,
            endereco_servico = :endereco_servico,
            descricao = :descricao,
            pecas_previstas = :pecas_previstas,
            data_execucao = :data_execucao,
            executado_por = :executado_por,
            os_status_id = 3
         WHERE id = :id';
    }

    private function formatValidationErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) {
            foreach ((array)$fieldErrors as $message) {
                if (is_string($message)) {
                    $messages[] = $message;
                }
            }
        }
        return implode('; ', $messages);
    }
}