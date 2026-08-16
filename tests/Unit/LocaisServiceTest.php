<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LocaisServiceTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(\database::class);
    }

    public function testServiceCanBeInstantiated(): void
    {
        $svc = new \App\Services\LocaisService($this->dbMock);
        $this->assertInstanceOf(\App\Services\LocaisService::class, $svc);
    }

    public function testCreateThrowsOnEmptyData(): void
    {
        $svc = new \App\Services\LocaisService($this->dbMock);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('O campo nome é obrigatório.');
        $svc->create([]);
    }

    public function testCreateReturnsZeroOnFailedQuery(): void
    {
        $this->dbMock->method('getConnection')
            ->willThrowException(new \RuntimeException('sem conexao real (mock)'));
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->create([
            'nome' => 'Local Teste',
            'secretaria_id' => 1,
        ]);

        $this->assertEquals(0, $result);
    }

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $this->dbMock->method('getConnection')
            ->willThrowException(new \RuntimeException('sem conexao real (mock)'));
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->update(1, ['nome' => 'Novo Nome']);

        $this->assertTrue($result);
    }

    public function testUpdateReturnsFalseOnFailure(): void
    {
        $this->dbMock->method('getConnection')
            ->willThrowException(new \RuntimeException('sem conexao real (mock)'));
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->update(1, ['nome' => 'Novo Nome']);

        $this->assertFalse($result);
    }

    public function testUpdateReturnsFalseOnEmptyData(): void
    {
        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->update(1, []);

        $this->assertFalse($result);
    }

    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getById(999);

        $this->assertNull($result);
    }

    public function testGetByIdReturnsObjectWhenFound(): void
    {
        $expected = (object)['id' => 1, 'nome' => 'Local Teste'];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => [$expected]]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getById(1);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('Local Teste', $result->nome);
    }

    public function testGetAllReturnsEmptyOnError(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAllReturnsDataOnSuccess(): void
    {
        $expected = [
            (object)['id' => 1, 'nome' => 'Local A'],
            (object)['id' => 2, 'nome' => 'Local B'],
        ];
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => $expected]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getAll();

        $this->assertCount(2, $result);
    }

    public function testCountReturnsZeroOnError(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->count(['nome' => 'Teste']);

        $this->assertEquals(0, $result);
    }

    public function testCountReturnsTotalOnSuccess(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'success', 'data' => [(object)['total' => 5]]]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->count();

        $this->assertEquals(5, $result);
    }

    public function testGetAllTiposLocaisReturnsEmptyOnError(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getAllTiposLocais();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAllSecretariasReturnsEmptyOnError(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getAllSecretarias();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAllRegioesReturnsEmptyOnError(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getAllRegioes();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAllLocaisReturnsEmptyOnError(): void
    {
        $this->dbMock->method('query')
            ->willReturn(['status' => 'error', 'data' => []]);

        $svc = new \App\Services\LocaisService($this->dbMock);
        $result = $svc->getAllLocais();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
