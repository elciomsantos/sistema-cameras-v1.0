<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EquipamentoServiceTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(\database::class);
    }

    public function testServiceCanBeInstantiated(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $this->assertInstanceOf(\EquipamentoService::class, $svc);
    }

    public function testExtractCommonDataReturnsDefaults(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData([]);

        $this->assertArrayHasKey('status_id', $result);
        $this->assertEquals(0, $result['status_id']);
        $this->assertArrayHasKey('observacao', $result);
        $this->assertNull($result['observacao']);
        $this->assertArrayHasKey('patrimonio', $result);
        $this->assertNull($result['patrimonio']);
    }

    public function testExtractCommonDataPreservesValues(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData([
            'status_id' => 1,
            'secretaria_id' => 2,
            'observacao' => 'Teste',
            'ip' => '192.168.1.1',
            'uf' => 'PR',
            'cep' => '86010-000',
        ]);

        $this->assertEquals(1, $result['status_id']);
        $this->assertEquals(2, $result['secretaria_id']);
        $this->assertEquals('Teste', $result['observacao']);
        $this->assertEquals('192.168.1.1', $result['normalized_ip']);
        $this->assertEquals('PR', $result['uf']);
        $this->assertEquals('86010000', $result['cep']);
    }

    public function testExtractCommonDataSanitizesUf(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData(['uf' => 'pr']);
        $this->assertEquals('PR', $result['uf']);

        $result = $svc->extractCommonData(['uf' => 'PARANA']);
        $this->assertNull($result['uf']);

        $result = $svc->extractCommonData([]);
        $this->assertNull($result['uf']);
    }

    public function testExtractCommonDataSanitizesCep(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData(['cep' => '86010-000']);
        $this->assertEquals('86010000', $result['cep']);

        $result = $svc->extractCommonData(['cep' => '12345']);
        $this->assertNull($result['cep']);

        $result = $svc->extractCommonData([]);
        $this->assertNull($result['cep']);
    }

    public function testExtractCommonDataHandlesDvrDecimal(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData(['dvr_armazenamento_tb' => '4,5']);
        $this->assertEquals(4.5, $result['dvr_armazenamento_tb']);

        $result = $svc->extractCommonData([]);
        $this->assertNull($result['dvr_armazenamento_tb']);
    }

    public function testValidateRequiredIdsThrowsOnMissing(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $this->expectException(\RuntimeException::class);
        $svc->validateRequiredIds([]);
    }

    public function testValidateRequiredIdsPassesOnValid(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $data = [
            'status_id' => 1,
            'procedimento_id' => 1,
            'regiao_id' => 1,
            'tipo_id' => 1,
            'secretaria_id' => 1,
            'marca_id' => 1,
        ];
        $svc->validateRequiredIds($data);
        $this->assertTrue(true);
    }

    public function testValidateCoordenadasAcceptsValid(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $svc->validateCoordenadas('-23.3105, -51.1628');
        $svc->validateCoordenadas('23.3105,51.1628');
        $svc->validateCoordenadas(null);
        $this->assertTrue(true);
    }

    public function testValidateCoordenadasRejectsInvalid(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $this->expectException(\Exception::class);
        $svc->validateCoordenadas('invalid');
    }

    public function testValidateTipoSpecificDvrRequiresModelo(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $this->expectException(\Exception::class);
        $svc->validateTipoSpecific(3, null, null);
    }

    public function testValidateTipoSpecificTotemRequiresCameras(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $this->expectException(\Exception::class);
        $svc->validateTipoSpecific(4, null, null);
    }

    public function testBuildEquipDataReturnsStructuredArray(): void
    {
        $svc = new \EquipamentoService($this->dbMock);
        $fields = [
            'tipo_id' => 1,
            'tipo_camera_id' => null,
            'status_id' => 1,
            'procedimento_id' => 1,
            'regiao_id' => 1,
            'secretaria_id' => 1,
            'marca_id' => 1,
            'normalized_ip' => null,
            'patrimonio' => null,
            'numero_serie' => null,
            'transmissao_id' => null,
            'origem_link_id' => null,
            'inscricao_link' => null,
            'data_instalacao' => null,
            'observacao' => null,
        ];
        $result = $svc->buildEquipData($fields, 1, 1);
        $this->assertArrayHasKey(':tipo_equipamento_id', $result);
        $this->assertEquals(1, $result[':local_id']);
    }
}
