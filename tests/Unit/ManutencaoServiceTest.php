<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exception\ServiceException;
use PHPUnit\Framework\TestCase;

class ManutencaoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../api/v2/api_manutencao_utils.php';
    }

    private function camerasConfig(): array
    {
        return [
            'table' => 'equipamentos_manutencoes',
            'entity_id_param' => 'equipamento_id',
            'entity_column' => 'equipamento_id',
            'entity_title' => 'Câmera',
            'entity_resource' => 'câmera',
            'scope_verifier' => fn(): bool => true,
            'scope_message' => 'Câmera não pertence à sua secretaria.',
            'mismatch_message' => 'A ordem de serviço selecionada não corresponde à câmera informada.',
            'audit' => false,
            'validate_numero_os' => true,
            'list_payload_key' => 'cameras',
            'list_cache_key' => null,
            'list_sql' => 'SELECT e.id FROM equipamentos e WHERE e.deleted_at IS NULL',
            'pending_sql' => 'SELECT m.id FROM equipamentos_manutencoes m WHERE m.os_status_id = 1 {{where}} ORDER BY m.created_at DESC LIMIT 200',
            'executing_sql' => 'SELECT m.id FROM equipamentos_manutencoes m WHERE m.os_status_id = 2 {{where}} ORDER BY m.updated_at DESC, m.created_at DESC LIMIT 200',
            'count_sql' => 'SELECT COUNT(*) AS total FROM equipamentos_manutencoes m WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {{historico_where}}',
            'history_sql' => 'SELECT m.id FROM equipamentos_manutencoes m WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {{historico_where}} {{order}} LIMIT ? OFFSET ?',
            'sort_map' => ['data_hora' => 'COALESCE(m.data_execucao, m.data_hora, m.created_at)'],
            'search_builder' => fn(array $whereParts, array $params, string $busca): array => $whereParts,
            'history_search_builder' => fn(array $historicoParts, array $historicoParams, string $busca): array => $historicoParts,
            'create_rules' => ['equipamento_id' => 'required|numeric|min:1'],
            'create_entity_lookup' => 'SELECT id, secretaria_id FROM equipamentos WHERE id = :id LIMIT 1',
            'create_insert' => function (array $payload, array $entityData, string $dataHora, int $usuarioId, int $entityId): array {
                $secretariaId = (int)($entityData['secretaria_id'] ?? 0) > 0 ? (int)$entityData['secretaria_id'] : null;
                return [
                    'INSERT INTO equipamentos_manutencoes
                        (equipamento_id, secretaria_id, numero_os, problemas, data_hora, descricao, created_by, os_status_id)
                     VALUES
                        (:equipamento_id, :secretaria_id, :numero_os, :problemas, :data_hora, :descricao, :created_by, 1)',
                    [
                        ':equipamento_id' => $entityId,
                        ':secretaria_id' => $secretariaId,
                        ':numero_os' => trim((string)($payload['numero_os'] ?? '')) ?: null,
                        ':problemas' => trim((string)($payload['problemas'] ?? '')),
                        ':data_hora' => $dataHora,
                        ':descricao' => '',
                        ':created_by' => $usuarioId,
                    ],
                ];
            },
        ];
    }

    private function alarmesConfig(): array
    {
        return [
            'table' => 'alarmes_manutencoes',
            'entity_id_param' => 'alarme_id',
            'entity_column' => 'alarme_id',
            'entity_title' => 'Alarme',
            'entity_resource' => 'alarme',
            'scope_verifier' => fn(): bool => true,
            'scope_message' => 'Alarme não pertence à sua secretaria.',
            'mismatch_message' => 'A ordem de serviço selecionada não corresponde ao alarme informado.',
            'audit' => true,
            'validate_numero_os' => false,
            'list_payload_key' => 'alarme',
            'list_cache_key' => null,
            'list_sql' => 'SELECT a.id FROM central_alarmes a LIMIT 1000',
            'pending_sql' => 'SELECT m.id FROM alarmes_manutencoes m WHERE m.os_status_id = 1 {{where}} ORDER BY m.created_at DESC LIMIT 200',
            'executing_sql' => 'SELECT m.id FROM alarmes_manutencoes m WHERE m.os_status_id = 2 {{where}} ORDER BY m.updated_at DESC LIMIT 200',
            'count_sql' => 'SELECT COUNT(*) AS total FROM alarmes_manutencoes m WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {{historico_where}}',
            'history_sql' => 'SELECT m.id FROM alarmes_manutencoes m WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {{historico_where}} {{order}} LIMIT ? OFFSET ?',
            'sort_map' => ['data_hora' => 'COALESCE(m.data_execucao, m.data_hora, m.created_at)'],
            'search_builder' => fn(array $whereParts, array $params, string $busca): array => $whereParts,
            'history_search_builder' => fn(array $historicoParts, array $historicoParams, string $busca): array => $historicoParts,
            'create_rules' => ['alarme_id' => 'required|numeric|min:1'],
            'create_entity_lookup' => 'SELECT id FROM central_alarmes WHERE id = :id LIMIT 1',
            'create_insert' => function (array $payload, array $entityData, string $dataHora, int $usuarioId, int $entityId): array {
                return [
                    'INSERT INTO alarmes_manutencoes
                        (alarme_id, numero_os, problemas, data_hora, descricao, created_by, os_status_id)
                     VALUES
                        (:alarme_id, :numero_os, :problemas, :data_hora, :descricao, :created_by, 1)',
                    [
                        ':alarme_id' => $entityId,
                        ':numero_os' => trim((string)($payload['numero_os'] ?? '')) ?: null,
                        ':problemas' => trim((string)($payload['problemas'] ?? '')),
                        ':data_hora' => $dataHora,
                        ':descricao' => '',
                        ':created_by' => $usuarioId,
                    ],
                ];
            },
        ];
    }

    public function testServiceCanBeInstantiated(): void
    {
        $dbMock = $this->createMock(\database::class);
        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());
        $this->assertInstanceOf(\App\Services\ManutencaoService::class, $svc);
    }

    public function testLoadPayloadBuildsExpectedStructure(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (stripos($sql, 'COUNT(*) AS total') !== false) {
                return ['status' => 'success', 'data' => [(object)['total' => 3]]];
            }
            return ['status' => 'success', 'data' => [(object)['id' => 1]]];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());
        $payload = $svc->loadPayload([
            'equipamento_id' => 5,
            'page' => 1,
            'per_page' => 20,
            'busca' => 'teste',
            'include_lists' => true,
        ]);

        $this->assertArrayHasKey('cameras', $payload);
        $this->assertArrayHasKey('status_options', $payload);
        $this->assertArrayHasKey('procedimento_options', $payload);
        $this->assertArrayHasKey('defaults', $payload);
        $this->assertArrayHasKey('pending_orders', $payload);
        $this->assertArrayHasKey('executing_orders', $payload);
        $this->assertArrayHasKey('historico', $payload);
        $this->assertSame(3, $payload['pagination']['total']);
        $this->assertSame(1, $payload['pagination']['total_pages']);
        $this->assertSame('DESC', $payload['sorting']['sort_dir']);
    }

    public function testCreateOsSuccess(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'FROM equipamentos') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'success', 'data' => [(object)['id' => 5, 'secretaria_id' => 3]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());
        $svc->createOs([
            'equipamento_id' => 5,
            'problemas' => 'Câmera sem imagem há 3 dias.',
            'data_hora' => '2026-08-16T10:00',
        ], 2);

        $this->addToAssertionCount(1);
    }

    public function testCreateOsRejectsShortProblemas(): void
    {
        $dbMock = $this->createMock(\database::class);
        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());

        try {
            $svc->createOs(['equipamento_id' => 5, 'problemas' => 'abc'], 2);
            $this->fail('Esperava ServiceException');
        } catch (ServiceException $e) {
            $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
        }
    }

    public function testCreateOsRejectsInvalidNumeroOs(): void
    {
        $dbMock = $this->createMock(\database::class);
        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());

        try {
            $svc->createOs([
                'equipamento_id' => 5,
                'problemas' => 'Problema sério detectado na câmera.',
                'numero_os' => 'os#1!',
            ], 2);
            $this->fail('Esperava ServiceException');
        } catch (ServiceException $e) {
            $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
        }
    }

    public function testCreateOsThrowsNotFoundWhenEntityMissing(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'FROM equipamentos') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'error', 'data' => []];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());

        try {
            $svc->createOs([
                'equipamento_id' => 999,
                'problemas' => 'Problema sério detectado na câmera.',
            ], 2);
            $this->fail('Esperava ServiceException');
        } catch (ServiceException $e) {
            $this->assertSame('NOT_FOUND', $e->getErrorCode());
        }
    }

    public function testCreateOsThrowsForbiddenWhenScopeFails(): void
    {
        $config = array_merge($this->camerasConfig(), ['scope_verifier' => fn(): bool => false]);
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'FROM equipamentos') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'success', 'data' => [(object)['id' => 5, 'secretaria_id' => 3]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $config);

        try {
            $svc->createOs([
                'equipamento_id' => 5,
                'problemas' => 'Problema sério detectado na câmera.',
            ], 2);
            $this->fail('Esperava ServiceException');
        } catch (ServiceException $e) {
            $this->assertSame('FORBIDDEN', $e->getErrorCode());
        }
    }

    public function testCreateOsAlarmesCallsAuditTriggers(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->expects($this->once())->method('setUserContext');
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'FROM central_alarmes')) {
                return ['status' => 'success', 'data' => [(object)['id' => 5]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->alarmesConfig());
        $svc->createOs([
            'alarme_id' => 5,
            'problemas' => 'Alarme sem comunicação com a central.',
            'data_hora' => '2026-08-16T09:30',
        ], 3);

        $this->addToAssertionCount(1);
    }

    public function testStartOsSuccess(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'success', 'data' => [(object)['id' => 10, 'equipamento_id' => 5, 'os_status_id' => 1]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());
        $svc->startOs(['os_id' => 10, 'equipamento_id' => 5], 2);

        $this->addToAssertionCount(1);
    }

    public function testStartOsRejectsWrongEntity(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'success', 'data' => [(object)['id' => 10, 'equipamento_id' => 99, 'os_status_id' => 1]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());

        try {
            $svc->startOs(['os_id' => 10, 'equipamento_id' => 5], 2);
            $this->fail('Esperava ServiceException');
        } catch (ServiceException $e) {
            $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
        }
    }

    public function testStartOsRejectsNonPendingStatus(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'success', 'data' => [(object)['id' => 10, 'equipamento_id' => 5, 'os_status_id' => 3]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());

        try {
            $svc->startOs(['os_id' => 10, 'equipamento_id' => 5], 2);
            $this->fail('Esperava ServiceException');
        } catch (ServiceException $e) {
            $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
        }
    }

    public function testFinalizeOsSuccessCommitsAndRunsFinalizeExtra(): void
    {
        $extraCalls = 0;
        $extraArgs = null;
        $config = array_merge($this->camerasConfig(), [
            'finalize_extra' => function (\database $db, int $entityId, int $osId, ?int $statusId, int $usuarioId, string $descricao) use (&$extraCalls, &$extraArgs): void {
                $extraCalls++;
                $extraArgs = [$entityId, $osId, $statusId, $usuarioId, $descricao];
            },
        ]);

        $dbMock = $this->createMock(\database::class);
        $dbMock->expects($this->once())->method('beginTransaction');
        $dbMock->expects($this->once())->method('commit');
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'success', 'data' => [(object)['id' => 10, 'equipamento_id' => 5, 'os_status_id' => 2]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $config);
        $svc->finalizeOs([
            'os_id' => 10,
            'equipamento_id' => 5,
            'descricao' => 'Manutenção concluída com sucesso na câmera.',
            'status_id' => 4,
            'procedimento_id' => 2,
            'data_hora' => '2026-08-16 14:30:00',
        ], 2);

        $this->assertSame(1, $extraCalls);
        $this->assertSame([5, 10, 4, 2, 'Manutenção concluída com sucesso na câmera.'], $extraArgs);
    }

    public function testFinalizeOsRejectsNonExecutingStatus(): void
    {
        $dbMock = $this->createMock(\database::class);
        $dbMock->method('query')->willReturnCallback(function (string $sql) {
            if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'WHERE id = :id')) {
                return ['status' => 'success', 'data' => [(object)['id' => 10, 'equipamento_id' => 5, 'os_status_id' => 1]]];
            }
            return ['status' => 'success', 'data' => []];
        });

        $svc = new \App\Services\ManutencaoService($dbMock, $this->camerasConfig());

        try {
            $svc->finalizeOs([
                'os_id' => 10,
                'equipamento_id' => 5,
                'descricao' => 'Manutenção concluída com sucesso na câmera.',
            ], 2);
            $this->fail('Esperava ServiceException');
        } catch (ServiceException $e) {
            $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
        }
    }
}