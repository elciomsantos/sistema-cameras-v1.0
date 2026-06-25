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
        $svc = new \AlarmeService($this->dbMock);
        $this->assertInstanceOf(\AlarmeService::class, $svc);
    }

    public function testValidateInputRejectsMissingConta(): void
    {
        $this->expectException(\RuntimeException::class);

        $svc = new \AlarmeService($this->dbMock);
        $svc->validateInput(['local' => 'Local Teste', 'status' => 'Ativo'], false);
    }

    public function testValidateInputRejectsMissingLocal(): void
    {
        $this->expectException(\RuntimeException::class);

        $svc = new \AlarmeService($this->dbMock);
        $svc->validateInput(['conta' => 123, 'status' => 'Ativo'], false);
    }

    public function testValidateInputWithRequiredId(): void
    {
        $this->expectException(\RuntimeException::class);

        $svc = new \AlarmeService($this->dbMock);
        $svc->validateInput(['conta' => 123, 'local' => 'Teste', 'status' => 'Ativo'], true);
    }

    public function testBuildParamsStructure(): void
    {
        $svc = new \AlarmeService($this->dbMock);
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
        $svc = new \AlarmeService($this->dbMock);
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
        $statusResult = [
            'status' => 'success',
            'data' => [(object)['id' => 1]],
            'affected_rows' => 1,
        ];

        $this->dbMock->method('query')
            ->willReturn($statusResult);

        $svc = new \AlarmeService($this->dbMock);
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
}
