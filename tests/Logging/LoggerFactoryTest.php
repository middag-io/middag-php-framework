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

use Middag\Framework\Logging\Contract\SecretRedactorInterface;
use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The PSR-3 logger factory: per-tuple Monolog loggers with caching, the
 * disabled NullLogger path, custom redactor wiring, and the null helpers.
 *
 * @internal
 */
#[CoversClass(LoggerFactory::class)]
final class LoggerFactoryTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/middag_logtest_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            @rmdir($this->basePath);
        }
    }

    #[Test]
    public function enabledFactoryBuildsAMonologLoggerNamedFromTheTuple(): void
    {
        $factory = $this->factory(enabled: true);

        $logger = $factory->forChannel('billing', 'audit');

        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('billing/audit', $logger->getName());
    }

    #[Test]
    public function forChannelDefaultsToCoreSystemTuple(): void
    {
        $logger = $this->factory(enabled: true)->forChannel();

        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('core/system', $logger->getName());
    }

    #[Test]
    public function sameTupleReturnsTheCachedInstance(): void
    {
        $factory = $this->factory(enabled: true);

        $first = $factory->forChannel('billing', 'audit');
        $second = $factory->forChannel('billing', 'audit');
        $other = $factory->forChannel('billing', 'system');

        self::assertSame($first, $second);
        self::assertNotSame($first, $other);
    }

    #[Test]
    public function disabledFactoryReturnsACachedNullLogger(): void
    {
        $factory = $this->factory(enabled: false);

        $logger = $factory->forChannel('billing', 'audit');

        self::assertInstanceOf(NullLogger::class, $logger);
        self::assertSame($logger, $factory->forChannel('billing', 'audit'));
    }

    #[Test]
    public function acceptsACustomRedactorWithoutChangingTheReturnType(): void
    {
        $redactor = new class implements SecretRedactorInterface {
            public function redact(array $context): array
            {
                return $context;
            }
        };

        $factory = new LoggerFactory(
            $this->basePath,
            new NullActorResolver(),
            new NullOriginResolver(),
            true,
            $redactor,
        );

        self::assertInstanceOf(Logger::class, $factory->forChannel('core', 'system'));
    }

    #[Test]
    public function nullHelpersReturnTheDisabledCollaborators(): void
    {
        $factory = $this->factory(enabled: true);

        self::assertInstanceOf(NullLogger::class, $factory->nullLogger());
        self::assertInstanceOf(NullHandler::class, $factory->disabledHandler());
    }

    private function factory(bool $enabled): LoggerFactory
    {
        return new LoggerFactory(
            $this->basePath,
            new NullActorResolver(),
            new NullOriginResolver(),
            $enabled,
        );
    }
}
