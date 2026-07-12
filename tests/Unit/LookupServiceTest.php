<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LookupServiceTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(\database::class);
        // Limpa cache APCu entre testes para evitar vazamento de estado entre casos
        // quando APCu esta habilitado no ambiente de testes.
        if (function_exists('\\apcu_clear_cache')) {
            \apcu_clear_cache();
        }
    }

    public function testServiceCanBeInstantiated(): void
    {
        $svc = new \LookupService($this->dbMock);
        $this->assertInstanceOf(\LookupService::class, $svc);
    }

    public function testGetTableDataReturnsEmptyForNonAllowed(): void
    {
        $svc = new \LookupService($this->dbMock);
        $result = $svc->getTableData('non_allowed_table');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetTableDataReturnsDataOnSuccess(): void
    {
        $expected = [(object)['id' => 1, 'nome' => 'Ativo']];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => $expected]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getTableData('status');

        $this->assertCount(1, $result);
        $this->assertEquals('Ativo', $result[0]->nome);
    }

    public function testGetTableDataReturnsEmptyOnError(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getTableData('status');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetMultipleTableDataReturnsStructured(): void
    {
        $this->dbMock->method('query')
            ->willReturnCallback(function ($sql) {
                if (str_contains($sql, 'status')) {
                    return ['status' => 'success', 'data' => [(object)['id' => 1, 'nome' => 'Ativo']]];
                }
                return ['status' => 'success', 'data' => []];
            });

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getMultipleTableData(['status' => 'status', 'regioes' => 'regioes']);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('regioes', $result);
    }

    public function testGetDadosCadastroCameraReturnsAllSections(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => []]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getDadosCadastroCamera();

        $expectedKeys = ['status', 'procedimentos', 'regioes', 'tipos_equipamento',
            'tipo_cameras', 'transmissoes', 'origem_links', 'marcas',
            'locais', 'tipos_locais', 'secretarias'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    public function testGetStatusListReturnsData(): void
    {
        $expected = [(object)['id' => 1, 'nome' => 'Ativo']];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => $expected]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getStatusList();

        $this->assertCount(1, $result);
    }

    public function testGetRegioesListReturnsData(): void
    {
        $expected = [(object)['id' => 1, 'nome' => 'CENTRO']];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => $expected]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getRegioesList();

        $this->assertCount(1, $result);
    }

    public function testGetModelosAlarmesReturnsData(): void
    {
        $expected = [(object)['id' => 1, 'nome' => 'INTELBRAS AMT 8000']];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => $expected]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getModelosAlarmes();

        $this->assertCount(1, $result);
    }

    public function testGetContasAlarmesReturnsData(): void
    {
        $expected = [(object)['conta' => 123, 'local' => 'Escola A']];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => $expected]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getContasAlarmes();

        $this->assertCount(1, $result);
        $this->assertEquals(123, $result[0]->conta);
    }

    public function testGetModelosCamerasReturnsDataForMarca(): void
    {
        $expected = [(object)['id' => 1, 'nome' => 'Intelbras D1M']];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => $expected]);

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getModelosCameras(5);

        $this->assertCount(1, $result);
        $this->assertEquals('Intelbras D1M', $result[0]->nome);
    }

    public function testGetModelosCamerasWithTipoFiltersQuery(): void
    {
        $expected = [(object)['id' => 1, 'nome' => 'DVR 16CH']];

        $this->dbMock->method('query')
            ->willReturnCallback(function ($sql, $params = []) use ($expected) {
                // Confirma que tipo_id aplica filtro AND tipo_equipamento_id
                if (str_contains($sql, 'tipo_equipamento_id') && isset($params[':tipo_id'])) {
                    return ['status' => 'success', 'data' => $expected];
                }
                return ['status' => 'success', 'data' => []];
            });

        $svc = new \LookupService($this->dbMock);
        $result = $svc->getModelosCameras(5, 2);

        $this->assertCount(1, $result);
    }
}
