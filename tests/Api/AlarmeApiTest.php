<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

class AlarmeApiTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost:8080';
    }

    public function testApiAlarmesGetWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_alarmes');

        $this->assertEquals(401, $httpCode);
    }

    public function testApiPingReturnsOk(): void
    {
        $data = $this->requestJson('/index.php?page=api/api_ping');

        if ($data === null) {
            $this->markTestSkipped('API server is not reachable at ' . $this->baseUrl);
        }

        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('ok', $data['data']['status'] ?? '');
    }

    public function testApiHealthCheck(): void
    {
        $data = $this->requestJson('/index.php?page=api/api_health');

        if ($data === null) {
            $this->markTestSkipped('API server is not reachable at ' . $this->baseUrl);
        }

        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
    }

    public function testApiCadastrarAlarmesWithoutCsrfReturnsError(): void
    {
        $url = $this->baseUrl . '/index.php?page=api/api_cadastrar_alarmes';
        $payload = json_encode(['conta' => 99999, 'local' => 'Test', 'status' => 'Ativo']);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static function (): bool { return true; });
        try {
            $response = @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? null;
        } finally {
            restore_error_handler();
        }

        if (!$headers || !isset($headers[0])) {
            $this->markTestSkipped('API server is not reachable at ' . $this->baseUrl);
        }

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testApiHealthRequiresAuth(): void
    {
        $data = $this->requestJson('/index.php?page=api/api_health');

        if ($data === null) {
            $this->markTestSkipped('API server is not reachable at ' . $this->baseUrl);
        }

        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    private function requestStatus(string $path, string $method = 'GET'): int
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $headers = null;
        set_error_handler(static function (): bool { return true; });
        try {
            @file_get_contents($this->baseUrl . $path, false, $context);
            $headers = $http_response_header ?? null;
        } finally {
            restore_error_handler();
        }

        if (!$headers || !isset($headers[0])) {
            $this->markTestSkipped("API server is not reachable at {$this->baseUrl}");
        }

        preg_match('#HTTP/\d(?:\.\d)?\s+(\d+)#', $headers[0], $matches);

        return (int)($matches[1] ?? 0);
    }

    private function requestJson(string $path): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static function (): bool { return true; });
        try {
            $content = @file_get_contents($this->baseUrl . $path, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
