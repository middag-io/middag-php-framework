<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging;

use Middag\Framework\Logging\ErrorLogFallbackLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ErrorLogFallbackLogger::class)]
final class ErrorLogFallbackLoggerTest extends TestCase
{
    private string $logFile;

    private string $previousDestination;

    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'middag-errorlog-');
        $this->previousDestination = (string) ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousDestination);
        @unlink($this->logFile);
    }

    #[Test]
    public function interpolatesContextAndAppendsLeftoverAsJson(): void
    {
        (new ErrorLogFallbackLogger('adapter'))->warning('user {id} failed', ['id' => 42, 'ip' => '127.0.0.1']);

        $line = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('[adapter.warning] user 42 failed {"ip":"127.0.0.1"}', $line);
    }

    #[Test]
    public function defaultChannelAndLevelPrefixTheLine(): void
    {
        (new ErrorLogFallbackLogger())->error('boom');

        self::assertStringContainsString('[middag.error] boom', (string) file_get_contents($this->logFile));
    }

    #[Test]
    public function nonScalarInterpolationFallsBackToJson(): void
    {
        (new ErrorLogFallbackLogger())->info('payload {data}', ['data' => ['a' => 1]]);

        self::assertStringContainsString('payload {"a":1}', (string) file_get_contents($this->logFile));
    }
}
