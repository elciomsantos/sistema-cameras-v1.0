<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    private const TEST_REQUEST_ID = 'req_test_123';

    public static function setUpBeforeClass(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2));
        }
    }

    public function testGetRequestIdReturnsString(): void
    {
        $id = \App\Api\ApiResponse::getRequestId();
        $this->assertIsString($id);
        $this->assertStringStartsWith('req_', $id);
    }

    public function testGetRequestIdIsUnique(): void
    {
        \App\Api\ApiResponse::resetRequestId();
        $id1 = \App\Api\ApiResponse::getRequestId();
        \App\Api\ApiResponse::resetRequestId();
        $id2 = \App\Api\ApiResponse::getRequestId();
        $this->assertNotEquals($id1, $id2);
    }

    public function testSuccessReturnsExpectedStructure(): void
    {
        $data = ['id' => 1, 'nome' => 'Teste'];
        ob_start();
        \App\Api\ApiResponse::success($data);
        $output = ob_get_clean();

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('SUCCESS', $result['code']);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals('Teste', $result['data']['nome']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('timestamp', $result['meta']);
        $this->assertArrayHasKey('version', $result['meta']);
    }

    public function testCreatedReturnsExpectedStructure(): void
    {
        ob_start();
        \App\Api\ApiResponse::created(['message' => 'Criado'], 42);
        $output = ob_get_clean();

        $result = json_decode($output, true);
        $this->assertTrue($result['success']);
        $this->assertEquals('CREATED', $result['code']);
        $this->assertEquals(42, $result['data']['id']);
    }

    public function testErrorReturnsExpectedStructure(): void
    {
        ob_start();
        \App\Api\ApiResponse::error('BAD_REQUEST', 'Erro de validação');
        $output = ob_get_clean();

        $result = json_decode($output, true);
        $this->assertFalse($result['success']);
        $this->assertEquals('BAD_REQUEST', $result['code']);
        $this->assertEquals('Erro de validação', $result['message']);
    }

    public function testNotFoundReturnsExpected(): void
    {
        ob_start();
        \App\Api\ApiResponse::notFound('resource', 'user_123');
        $output = ob_get_clean();

        $result = json_decode($output, true);
        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_FOUND', $result['code']);
    }

    public function testValidationErrorReturnsExpected(): void
    {
        $errors = ['nome' => ['Campo obrigatório']];
        ob_start();
        \App\Api\ApiResponse::validationError($errors);
        $output = ob_get_clean();

        $result = json_decode($output, true);
        $this->assertFalse($result['success']);
        $this->assertEquals('VALIDATION_ERROR', $result['code']);
        $this->assertEquals($errors, $result['data']['errors']);
    }
}
