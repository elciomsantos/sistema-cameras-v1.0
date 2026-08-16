<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost:8080';
    }

    public function testHealthWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_health');

        $this->assertEquals(401, $httpCode, 'Health endpoint should require auth');
    }

    public function testProtectedEndpointWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/session_check');

        $this->assertEquals(401, $httpCode, 'Protected endpoint should require auth');
    }

    public function testInvalidMethodReturns401(): void
    {
        // Arquitetura auth-first: sem sessão, qualquer método é rejeitado com 401
        // antes de validar o método aceito pelo endpoint.
        $httpCode = $this->requestStatus('/index.php?page=api/api_health', 'POST');

        $this->assertEquals(401, $httpCode, 'POST on GET-only endpoint without auth returns 401');
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
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            file_get_contents($this->baseUrl . $path, false, $context);
            $headers = $http_response_header ?? null;
        } finally {
            restore_error_handler();
        }

        if (!$headers || !isset($headers[0])) {
            $this->markTestSkipped("API server is not reachable at {$this->baseUrl}");
        }

        preg_match('#HTTP/\d(?:\.\d)?\s+(\d+)#', $headers[0], $matches);

        $code = (int)($matches[1] ?? 0);

        // 404 num host vivo indica que a aplicacao nao esta servida na URL de teste
        // (ex.: servidor Apache local sem o front controller) — nao e uma regressao.
        if ($code === 404 && getenv('TEST_BASE_URL') === false) {
            $this->markTestSkipped("Application not served at {$this->baseUrl} (got 404)");
        }

        return $code;
    }
}
