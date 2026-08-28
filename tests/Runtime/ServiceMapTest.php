<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Runtime;

use Middag\Framework\Runtime\ServiceMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @internal
 */
#[CoversClass(ServiceMap::class)]
final class ServiceMapTest extends TestCase
{
    #[Test]
    public function itIsAPsr11Container(): void
    {
        self::assertInstanceOf(ContainerInterface::class, new ServiceMap());
    }

    #[Test]
    public function anInstanceIsHandedBackAsIs(): void
    {
        $service = new stdClass();
        $map = new ServiceMap(['x' => $service]);

        self::assertTrue($map->has('x'));
        self::assertSame($service, $map->get('x'));
    }

    #[Test]
    public function anUnknownIdThrows(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        (new ServiceMap())->get('ghost');
    }

    /**
     * The point of allowing closures: a handler that opens a connection must do
     * it on the first message, not at boot.
     */
    #[Test]
    public function aClosureIsBuiltOnceOnFirstUse(): void
    {
        $builds = 0;
        $map = new ServiceMap([
            'x' => static function () use (&$builds): stdClass {
                ++$builds;

                return new stdClass();
            },
        ]);

        self::assertSame(0, $builds, 'Registering must not build anything.');

        $first = $map->get('x');

        self::assertSame(1, $builds);
        self::assertSame($first, $map->get('x'), 'A resolved service must be memoised.');
        self::assertSame(1, $builds);
    }

    #[Test]
    public function aClosureReceivesTheContainer(): void
    {
        $map = new ServiceMap([
            'dep' => 'value',
            'x' => static fn (ContainerInterface $c): string => 'saw:' . $c->get('dep'),
        ]);

        self::assertSame('saw:value', $map->get('x'));
    }

    /**
     * `with()` returns a new map: the kernel wires itself in without ever
     * mutating what the caller passed.
     */
    #[Test]
    public function withDoesNotMutateTheOriginal(): void
    {
        $original = new ServiceMap(['a' => 1]);
        $extended = $original->with('b', 2);

        self::assertFalse($original->has('b'));
        self::assertTrue($extended->has('a'));
        self::assertSame(2, $extended->get('b'));
    }
}
