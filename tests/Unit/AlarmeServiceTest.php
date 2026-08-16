<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AlarmeServiceTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(\database::class);
    }

    public function testServiceCanBeInstantiated(): void
    {
        $svc = new \App\Services\AlarmeService($this->dbMock);
        $this->assertInstanceOf(\App\Services\AlarmeService::class, $svc);
    }

    public function testBuildParamsStructure(): void
    {
        $svc = new \App\Services\AlarmeService($this->dbMock);
        $reflection = new \ReflectionMethod($svc, 'buildParams');

        $data = [
            'regiao' => 'CENTRO',
            'regiao_id' => 1,
            'conta' => 123,
            'status' => 'Ativo',
            'status_id' => 1,
            'local' => 'Local Teste',
            'endereco' => 'Rua A',
            'numero' => '100',
            'pgm1' => null,
            'pgm2' => null,
            'mac' => null,
            'ip' => null,
            'integracao' => null,
            'camera_gm' => 0,
            'quant_camera_gm' => null,
            'ip_dvr' => null,
            'cameras_dvr' => null,
            'modelo_alarme_id' => null,
            'quant_repetidor' => null,
            'qtde_sensores' => null,
            'documentacao' => null,
            'monitorada' => null,
            'numero_sei' => null,
            'data_atualizacao' => '2026-01-01',
            'observacao' => null,
        ];

        $params = $reflection->invoke($svc, $data);

        $this->assertArrayHasKey(':conta', $params);
        $this->assertArrayHasKey(':local', $params);
        $this->assertArrayHasKey(':status', $params);
        $this->assertEquals(123, $params[':conta']);
        $this->assertEquals('Local Teste', $params[':local']);
    }

    public function testBuildParamsWithId(): void
    {
        $svc = new \App\Services\AlarmeService($this->dbMock);
        $reflection = new \ReflectionMethod($svc, 'buildParams');

        $data = [
            'id' => 5,
            'regiao' => null,
            'regiao_id' => null,
            'conta' => 456,
            'status' => 'Inativo',
            'status_id' => 2,
            'local' => 'Outro Local',
            'endereco' => null,
            'numero' => null,
            'pgm1' => null,
            'pgm2' => null,
            'mac' => null,
            'ip' => null,
            'integracao' => null,
            'camera_gm' => 0,
            'quant_camera_gm' => null,
            'ip_dvr' => null,
            'cameras_dvr' => null,
            'modelo_alarme_id' => null,
            'quant_repetidor' => null,
            'qtde_sensores' => null,
            'documentacao' => null,
            'monitorada' => null,
            'numero_sei' => null,
            'data_atualizacao' => date('Y-m-d'),
            'observacao' => null,
        ];

        $params = $reflection->invoke($svc, $data, true);

        $this->assertArrayHasKey(':id', $params);
        $this->assertEquals(5, $params[':id']);
    }

    public function testValidateInputAcceptsValidData(): void
    {
        // Mock for getStatusPermitidos() - called inside validateInput
        $statusList = [
            'status' => 'success',
            'data' => [
                (object)['nome' => 'Ativo'],
                (object)['nome' => 'Inativo'],
                (object)['nome' => 'Desligado'],
                (object)['nome' => 'Manutenção'],
                (object)['nome' => 'Pendente'],
            ],
        ];

        // Mock for status lookup (id from nome)
        $statusLookup = [
            'status' => 'success',
            'data' => [(object)['id' => 1]],
        ];

        // Mock for regiao lookup
        $regiaoLookup = [
            'status' => 'success',
            'data' => [(object)['id' => 1]],
        ];

        // Set up sequential returns
        $this->dbMock->method('query')
            ->willReturnOnConsecutiveCalls($statusList, $statusLookup, $regiaoLookup);

        $svc = new \App\Services\AlarmeService($this->dbMock);
        $result = $svc->validateInput([
            'conta' => 789,
            'local' => 'Escola Municipal Centro',
            'status' => 'Ativo',
            'regiao' => 'CENTRO',
            'endereco' => 'Rua Principal, 100',
        ], false);

        $this->assertIsArray($result);
        $this->assertEquals(789, $result['conta']);
        $this->assertEquals('Escola Municipal Centro', $result['local']);
        $this->assertEquals('Ativo', $result['status']);
        $this->assertEquals(1, $result['status_id']);
    }

    public function testValidateInputProducesCompleteKeySet(): void
    {
        $statusList = [
            'status' => 'success',
            'data' => [
                (object)['nome' => 'Ativo'],
                (object)['nome' => 'Inativo'],
            ],
        ];
        $statusLookup = ['status' => 'success', 'data' => [(object)['id' => 1]]];
        $regiaoLookup = ['status' => 'success', 'data' => [(object)['id' => 2]]];

        $this->dbMock->method('query')
            ->willReturnOnConsecutiveCalls($statusList, $statusLookup, $regiaoLookup);

        $svc = new \App\Services\AlarmeService($this->dbMock);
        $result = $svc->validateInput([
            'conta' => 42,
            'local' => 'UBS Central',
            'status' => 'Ativo',
            'regiao' => 'NORTE',
            'id' => 7,
            'endereco' => 'Av. Principal',
            'numero' => '99',
            'bairro' => 'Centro',
            'cidade' => 'GML',
            'uf' => 'PR',
            'cep' => '86010-000',
            'local_id' => 3,
            'pgm1' => 'p1',
            'pgm2' => 'p2',
            'mac' => 'AA:BB:CC:DD:EE:FF',
            'ip' => '10.1.1.1',
            'integracao' => '2026-01-01',
            'camera_gm' => true,
            'quant_camera_gm' => 2,
            'ip_dvr' => '10.1.1.2',
            'cameras_dvr' => 4,
            'modelo_alarme_id' => 5,
            'quant_repetidor' => 1,
            'qtde_sensores' => 6,
            'documentacao' => 'doc',
            'monitorada' => 'sim',
            'numero_sei' => 'SEI-1',
            'data_atualizacao' => '2026-01-02',
            'observacao' => 'obs',
        ], true);

        $expectedKeys = [
            'id', 'conta', 'local', 'status', 'status_id', 'regiao', 'regiao_id',
            'endereco', 'numero', 'bairro', 'cidade', 'uf', 'cep', 'local_id',
            'pgm1', 'pgm2', 'mac', 'ip', 'integracao', 'camera_gm', 'quant_camera_gm',
            'ip_dvr', 'cameras_dvr', 'modelo_alarme_id', 'quant_repetidor', 'qtde_sensores',
            'documentacao', 'monitorada', 'numero_sei', 'data_atualizacao', 'observacao',
        ];
        sort($expectedKeys);
        $actualKeys = array_keys($result);
        sort($actualKeys);

        $this->assertSame($expectedKeys, $actualKeys, 'Conjunto de chaves de validateInput deve ser completo');
        $this->assertEquals(7, $result['id']);
        $this->assertEquals(2, $result['regiao_id']);
    }
}