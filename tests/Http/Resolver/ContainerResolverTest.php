<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Resolver;

use Middag\Framework\Http\Resolver\ContainerResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

/**
 * Resolves controller arguments by type-hint from the DI container, ignoring
 * builtins and non-named (union/intersection) types.
 *
 * @internal
 */
#[CoversClass(ContainerResolver::class)]
final class ContainerResolverTest extends TestCase
{
    #[Test]
    public function supportsAClassTypeThatTheContainerCanProvide(): void
    {
        $resolver = new ContainerResolver($this->container([ContainerResolverService::class => new ContainerResolverService()]));

        $this->assertTrue($resolver->supports($this->param('svc')));
    }

    #[Test]
    public function doesNotSupportABuiltinType(): void
    {
        $resolver = new ContainerResolver($this->container([]));

        $this->assertFalse($resolver->supports($this->param('count')));
    }

    #[Test]
    public function doesNotSupportAUnionType(): void
    {
        $resolver = new ContainerResolver($this->container([]));

        $this->assertFalse($resolver->supports($this->param('union')));
    }

    #[Test]
    public function doesNotSupportAClassTheContainerLacks(): void
    {
        $resolver = new ContainerResolver($this->container([]));

        $this->assertFalse($resolver->supports($this->param('svc')));
    }

    #[Test]
    public function resolveReturnsTheContainerEntry(): void
    {
        $service = new ContainerResolverService();
        $resolver = new ContainerResolver($this->container([ContainerResolverService::class => $service]));

        $this->assertSame($service, $resolver->resolve($this->param('svc'), []));
    }

    private function param(string $name): ReflectionParameter
    {
        foreach ((new ReflectionMethod(ContainerResolverController::class, 'action'))->getParameters() as $param) {
            if ($param->getName() === $name) {
                return $param;
            }
        }

        throw new RuntimeException('parameter not found: ' . $name);
    }

    /** @param array<string, object> $services */
    private function container(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, object> $services */
            public function __construct(private readonly array $services) {}

            public function get(string $id): object
            {
                return $this->services[$id]
                    ?? throw new RuntimeException('service not bound: ' . $id);
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}

final class ContainerResolverService {}

final class ContainerResolverController
{
    public function action(ContainerResolverService $svc, int $count, int|string $union): void {}
}
