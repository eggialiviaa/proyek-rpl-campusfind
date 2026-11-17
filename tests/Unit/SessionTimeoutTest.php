<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

class SessionTimeoutTest extends TestCase
{
    private ?string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = $_ENV['SESSION_LIFETIME'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv !== null) {
            $_ENV['SESSION_LIFETIME'] = $this->originalEnv;
            putenv("SESSION_LIFETIME={$this->originalEnv}");
        } else {
            unset($_ENV['SESSION_LIFETIME']);
            putenv('SESSION_LIFETIME');
        }
        parent::tearDown();
    }

    public function testDefaultTimeoutWhenNotSet(): void
    {
        unset($_ENV['SESSION_LIFETIME']);
        putenv('SESSION_LIFETIME');

        $timeout = AuthMiddleware::getSessionTimeout();
        $this->assertSame(7200, $timeout);
    }

    public function testDefaultTimeoutWhenEmptyString(): void
    {
        $_ENV['SESSION_LIFETIME'] = '';
        putenv('SESSION_LIFETIME=');

        $timeout = AuthMiddleware::getSessionTimeout();
        $this->assertSame(7200, $timeout);
    }

    public function testDefaultTimeoutWhenZero(): void
    {
        $_ENV['SESSION_LIFETIME'] = '0';
        putenv('SESSION_LIFETIME=0');

        $timeout = AuthMiddleware::getSessionTimeout();
        $this->assertSame(7200, $timeout);
    }

    public function testDefaultTimeoutWhenNegative(): void
    {
        $_ENV['SESSION_LIFETIME'] = '-300';
        putenv('SESSION_LIFETIME=-300');

        $timeout = AuthMiddleware::getSessionTimeout();
        $this->assertSame(7200, $timeout);
    }

    public function testValidStandardTimeout7200(): void
    {
        $_ENV['SESSION_LIFETIME'] = '7200';
        putenv('SESSION_LIFETIME=7200');

        $timeout = AuthMiddleware::getSessionTimeout();
        $this->assertSame(7200, $timeout);
    }

    public function testValidCustomPositiveTimeout(): void
    {
        $_ENV['SESSION_LIFETIME'] = '1800';
        putenv('SESSION_LIFETIME=1800');

        $timeout = AuthMiddleware::getSessionTimeout();
        $this->assertSame(1800, $timeout);
    }
}
