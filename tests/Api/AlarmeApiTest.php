<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

class AlarmeApiTest extends TestCase
{
    private string $apiBase;

    protected function setUp(): void
    {
        $this->apiBase = getenv('API_BASE_URL') ?: 'http://localhost/sistema-cameras-v1.0/public/index.php?page=api/';
    }

    public function testApiAlarmesGetWithoutAuthReturnsUnauthorized(): void
    {
        $url = $this->apiBase . 'api_alarmes';
        $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true]]);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->markTestSkipped('API server not available at ' . $this->apiBase);
        }

        $httpCode = 0;
        if (isset($http_response_header)) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $httpCode = (int)($matches[1] ?? 0);
        }

        $this->assertEquals(401, $httpCode);

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testApiAlarmesHealthCheck(): void
    {
        $url = $this->apiBase . 'api_health';
        $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true]]);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->markTestSkipped('API server not available');
        }

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
    }

    public function testApiPingReturnsOk(): void
    {
        $url = $this->apiBase . 'api_ping';
        $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true]]);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->markTestSkipped('API server not available');
        }

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('ok', $data['data']['status'] ?? '');
    }

    public function testApiCadastrarAlarmesWithoutCsrfReturnsError(): void
    {
        $url = $this->apiBase . 'api_cadastrar_alarmes';
        $payload = json_encode(['conta' => 99999, 'local' => 'Test', 'status' => 'Ativo']);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->markTestSkipped('API server not available');
        }

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testApiHealthRequiresAuth(): void
    {
        $url = $this->apiBase . 'api_health';
        $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true]]);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->markTestSkipped('API server not available');
        }

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }
}
