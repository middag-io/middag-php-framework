<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus;

use Middag\Framework\Bus\Middleware\ProfilingMiddleware;
use Middag\Framework\Observability\ProfileCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * The bus profiling middleware records each dispatched message, including
 * ones whose handler throws.
 *
 * @internal
 */
#[CoversClass(ProfilingMiddleware::class)]
final class ProfilingMiddlewareTest extends TestCase
{
    #[Test]
    public function recordsTheDispatchedMessageClassUnderTheBusCategory(): void
    {
        $collector = new ProfileCollector();
        $envelope = new Envelope(new stdClass());

        (new ProfilingMiddleware($collector))->handle($envelope, $this->terminalStack());

        $events = $collector->byCategory('bus');
        $this->assertCount(1, $events);
        $this->assertSame(stdClass::class, $events[0]['label']);
        $this->assertIsFloat($events[0]['duration_ms']);
    }

    #[Test]
    public function recordsEvenWhenTheHandlerThrows(): void
    {
        $collector = new ProfileCollector();

        try {
            (new ProfilingMiddleware($collector))->handle(new Envelope(new stdClass()), $this->throwingStack());
            $this->fail('exception should propagate');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertCount(1, $collector->byCategory('bus'));
    }

    private function terminalStack(): StackInterface
    {
        return new class implements StackInterface {
            public function next(): MiddlewareInterface
            {
                return new class implements MiddlewareInterface {
                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        return $envelope;
                    }
                };
            }
        };
    }

    private function throwingStack(): StackInterface
    {
        return new class implements StackInterface {
            public function next(): MiddlewareInterface
            {
                return new class implements MiddlewareInterface {
                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        throw new RuntimeException('handler failed');
                    }
                };
            }
        };
    }
}
