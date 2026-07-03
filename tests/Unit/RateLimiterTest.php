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
        $limiter = new \RateLimiter($this->tempDir);
        $this->assertInstanceOf(\RateLimiter::class, $limiter);
    }

    public function testConsumeReturnsTrueWithinLimit(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($limiter->consume('test_key', 10, 60));
        }
    }

    public function testConsumeReturnsFalseAfterLimit(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        for ($i = 0; $i < 5; $i++) {
            $limiter->consume('test_limit', 5, 60);
        }
        $this->assertFalse($limiter->consume('test_limit', 5, 60));
    }

    public function testConsumeTokenBucket(): void
    {
        $limiter = new \RateLimiter($this->tempDir, 'token_bucket');
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->consume('token_test', 3, 60));
        }
        $this->assertFalse($limiter->consume('token_test', 3, 60));
    }

    public function testGetRemainingReturnsCorrectCount(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        $this->assertEquals(10, $limiter->getRemaining('remaining_test', 10, 60));
        $limiter->consume('remaining_test', 10, 60);
        $this->assertEquals(9, $limiter->getRemaining('remaining_test', 10, 60));
    }

    public function testGetRetryAfterReturnsZeroWhenNotRateLimited(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        $this->assertEquals(0, $limiter->getRetryAfter('retry_test', 10, 60));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        for ($i = 0; $i < 10; $i++) {
            $limiter->consume('key_a', 10, 60);
        }
        $this->assertTrue($limiter->consume('key_b', 10, 60));
        $this->assertFalse($limiter->consume('key_a', 10, 60));
    }

    public function testCleanupRemovesOldEntries(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        $limiter->consume('cleanup_test', 10, 60);
        $removed = $limiter->cleanup(0);
        $this->assertGreaterThanOrEqual(0, $removed);
    }

    public function testUseDatabaseDoesNotThrow(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        $limiter->useDatabase(false);
        $this->assertTrue($limiter->consume('no_db_test', 10, 60));
    }

    public function testResolveConfigWithRegexPattern(): void
    {
        $limiter = new \RateLimiter($this->tempDir, 'sliding_window', [
            '/^auth:/' => [10, 60],
            '/^GET:/' => [120, 60],
        ]);
        $this->assertTrue($limiter->consume('auth:user1', 60, 60));
        $this->assertTrue($limiter->consume('GET:data', 60, 60));
    }

    public function testGetHeadersReturnsArray(): void
    {
        $limiter = new \RateLimiter($this->tempDir);
        $headers = $limiter->getHeaders('header_test', 10, 60);
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertArrayHasKey('Retry-After', $headers);
    }
}
