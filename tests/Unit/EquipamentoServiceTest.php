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
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $this->assertInstanceOf(\App\Services\EquipamentoService::class, $svc);
    }

    public function testExtractCommonDataReturnsDefaults(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
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
        $svc = new \App\Services\EquipamentoService($this->dbMock);
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
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData(['uf' => 'pr']);
        $this->assertEquals('PR', $result['uf']);

        $result = $svc->extractCommonData(['uf' => 'PARANA']);
        $this->assertNull($result['uf']);

        $result = $svc->extractCommonData([]);
        $this->assertNull($result['uf']);
    }

    public function testExtractCommonDataSanitizesCep(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData(['cep' => '86010-000']);
        $this->assertEquals('86010000', $result['cep']);

        $result = $svc->extractCommonData(['cep' => '12345']);
        $this->assertNull($result['cep']);

        $result = $svc->extractCommonData([]);
        $this->assertNull($result['cep']);
    }

    public function testExtractCommonDataHandlesDvrDecimal(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData(['dvr_armazenamento_tb' => '4,5']);
        $this->assertEquals(4.5, $result['dvr_armazenamento_tb']);

        $result = $svc->extractCommonData([]);
        $this->assertNull($result['dvr_armazenamento_tb']);
    }

    public function testValidateRequiredIdsThrowsOnMissing(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $this->expectException(\RuntimeException::class);
        $svc->validateRequiredIds([]);
    }

    public function testValidateRequiredIdsPassesOnValid(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $data = [
            'status_id' => 1,
            'procedimento_id' => 1,
            'regiao_id' => 1,
            'tipo_id' => 1,
            'secretaria_id' => 1,
            'marca_id' => 1,
        ];
        $svc->validateRequiredIds($data);
        $this->expectNotToPerformAssertions();
    }

    public function testValidateCoordenadasAcceptsValid(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $svc->validateCoordenadas('-23.3105, -51.1628');
        $svc->validateCoordenadas('23.3105,51.1628');
        $svc->validateCoordenadas(null);
        $this->expectNotToPerformAssertions();
    }

    public function testValidateCoordenadasRejectsInvalid(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $this->expectException(\Exception::class);
        $svc->validateCoordenadas('invalid');
    }

    public function testValidateTipoSpecificDvrRequiresModelo(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $this->expectException(\Exception::class);
        $svc->validateTipoSpecific(3, null, null);
    }

    public function testValidateTipoSpecificTotemRequiresCameras(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $this->expectException(\Exception::class);
        $svc->validateTipoSpecific(4, null, null);
    }

    public function testBuildEquipDataReturnsStructuredArray(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
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

    public function testExtractCommonDataProducesCompleteKeySet(): void
    {
        $svc = new \App\Services\EquipamentoService($this->dbMock);
        $result = $svc->extractCommonData([
            'status_id' => 1,
            'procedimento_id' => 2,
            'regiao_id' => 3,
            'tipo_id' => 4,
            'tipo_camera' => 5,
            'secretaria_id' => 6,
            'marca_id' => 7,
            'transmissao_id' => 8,
            'origem_link_id' => 9,
            'inscricao' => 'link',
            'descricao_posicao' => 'pos',
            'data_instalacao' => '2026-01-01',
            'ip' => '10.0.0.1',
            'classificacao_endereco_id' => 10,
            'logradouro' => 'Rua A',
            'bairro' => 'Centro',
            'cidade' => 'GML',
            'uf' => 'PR',
            'cep' => '86010-000',
            'numero' => '100',
            'tem_alarme' => true,
            'alarme_conta' => 123,
            'tem_camera' => false,
            'patrimonio' => 'pat-1',
            'serie_mac' => 'serie-1',
            'observacao' => 'obs',
            'mosaico' => 'm',
            'coordenadas' => 'c',
            'numero_ruas' => '2',
            'horario_funcionamento' => '24h',
            'lpr_sentido_via' => 'N',
            'lpr_faixa_monitorada' => '1',
            'lpr_leitura_noturna' => true,
            'lpr_url_acesso' => 'http://lpr',
            'dvr_modelo' => 'X',
            'dvr_canais' => 16,
            'dvr_armazenamento_tb' => '4,5',
            'totem_quantidade_cameras' => 4,
            'totem_tem_facial' => true,
            'totem_tem_lpr' => false,
        ]);

        $expectedKeys = [
            'status_id', 'procedimento_id', 'regiao_id', 'tipo_id', 'tipo_camera_id',
            'secretaria_id', 'marca_id', 'transmissao_id', 'origem_link_id',
            'inscricao_link', 'descricao_posicao', 'data_instalacao', 'normalized_ip',
            'classif_endereco_id', 'logradouro', 'bairro', 'cidade', 'uf', 'cep', 'numero',
            'tem_alarme', 'alarme_conta', 'tem_camera', 'patrimonio', 'numero_serie',
            'observacao', 'mosaico', 'coordenadas', 'numero_ruas', 'horario_funcionamento',
            'lpr_sentido_via', 'lpr_faixa_monitorada', 'lpr_leitura_noturna', 'lpr_url_acesso',
            'dvr_modelo', 'dvr_canais', 'dvr_armazenamento_tb', 'totem_quantidade_cameras',
            'totem_tem_facial', 'totem_tem_lpr',
        ];
        sort($expectedKeys);
        $actualKeys = array_keys($result);
        sort($actualKeys);

        $this->assertSame($expectedKeys, $actualKeys, 'Conjunto de chaves de extractCommonData deve ser completo');
        $this->assertEquals(4.5, $result['dvr_armazenamento_tb']);
        $this->assertEquals('PR', $result['uf']);
        $this->assertEquals('86010000', $result['cep']);
        $this->assertEquals(1, $result['tem_alarme']);
        $this->assertEquals(0, $result['tem_camera']);
    }
}
