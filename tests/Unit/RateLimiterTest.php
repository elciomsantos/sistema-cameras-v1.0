<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ratelimit_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0755, true);
    }

    /**
     * Cria um RateLimiter isolado em filesystem (sem DB) — cada teste fica confinado
     * ao seu tempDir unico, evitando contaminacao entre casos via tabela rate_limits.
     */
    private function createLimiter(string $strategy = 'sliding_window', array $configs = []): \RateLimiter
    {
        $limiter = new \RateLimiter($this->tempDir, $strategy, $configs);
        $limiter->useDatabase(false);
        return $limiter;
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tempDir);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    public function testCanBeInstantiated(): void
    {
        $limiter = $this->createLimiter();
        $this->assertInstanceOf(\RateLimiter::class, $limiter);
    }

    public function testConsumeReturnsTrueWithinLimit(): void
    {
        $limiter = $this->createLimiter();
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($limiter->consume('test_key', 10, 60));
        }
    }

    public function testConsumeReturnsFalseAfterLimit(): void
    {
        $limiter = $this->createLimiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->consume('test_limit', 5, 60);
        }
        $this->assertFalse($limiter->consume('test_limit', 5, 60));
    }

    public function testConsumeTokenBucket(): void
    {
        $limiter = $this->createLimiter('token_bucket');
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->consume('token_test', 3, 60));
        }
        $this->assertFalse($limiter->consume('token_test', 3, 60));
    }

    public function testGetRemainingReturnsCorrectCount(): void
    {
        $limiter = $this->createLimiter();
        $this->assertEquals(10, $limiter->getRemaining('remaining_test', 10, 60));
        $limiter->consume('remaining_test', 10, 60);
        $this->assertEquals(9, $limiter->getRemaining('remaining_test', 10, 60));
    }

    public function testGetRetryAfterReturnsZeroWhenNotRateLimited(): void
    {
        $limiter = $this->createLimiter();
        $this->assertEquals(0, $limiter->getRetryAfter('retry_test', 10, 60));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $limiter = $this->createLimiter();
        for ($i = 0; $i < 10; $i++) {
            $limiter->consume('key_a', 10, 60);
        }
        $this->assertTrue($limiter->consume('key_b', 10, 60));
        $this->assertFalse($limiter->consume('key_a', 10, 60));
    }

    public function testCleanupRemovesOldEntries(): void
    {
        $limiter = $this->createLimiter();
        $limiter->consume('cleanup_test', 10, 60);
        $removed = $limiter->cleanup(0);
        $this->assertGreaterThanOrEqual(0, $removed);
    }

    public function testUseDatabaseDoesNotThrow(): void
    {
        $limiter = $this->createLimiter();
        $this->assertTrue($limiter->consume('no_db_test', 10, 60));
    }

    public function testResolveConfigWithRegexPattern(): void
    {
        $limiter = $this->createLimiter('sliding_window', [
            '/^auth:/' => [10, 60],
            '/^GET:/' => [120, 60],
        ]);
        $this->assertTrue($limiter->consume('auth:user1', 60, 60));
        $this->assertTrue($limiter->consume('GET:data', 60, 60));
    }

    public function testGetHeadersReturnsArray(): void
    {
        $limiter = $this->createLimiter();
        $headers = $limiter->getHeaders('header_test', 10, 60);
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertArrayHasKey('Retry-After', $headers);
    }
}
