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

use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Resolver\InertiaResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use stdClass;

/**
 * Injects Inertia services listed in the supported allow-list, only when the
 * container actually provides them; everything else is declined.
 *
 * @internal
 */
#[CoversClass(InertiaResolver::class)]
final class InertiaResolverTest extends TestCase
{
    #[Test]
    public function supportsAllowListedClassPresentInTheContainer(): void
    {
        $resolver = new InertiaResolver(
            $this->container([InertiaResolverService::class => new InertiaResolverService()]),
            [InertiaResolverService::class],
        );

        $this->assertTrue($resolver->supports($this->param('svc')));
    }

    #[Test]
    public function doesNotSupportABuiltinType(): void
    {
        $resolver = new InertiaResolver($this->container([]), [InertiaResolverService::class]);

        $this->assertFalse($resolver->supports($this->param('n')));
    }

    #[Test]
    public function doesNotSupportAClassAbsentFromTheAllowList(): void
    {
        $resolver = new InertiaResolver(
            $this->container([InertiaResolverOther::class => new InertiaResolverOther()]),
            [InertiaResolverService::class],
        );

        $this->assertFalse($resolver->supports($this->param('other')));
    }

    #[Test]
    public function doesNotSupportAnAllowListedClassTheContainerLacks(): void
    {
        $resolver = new InertiaResolver($this->container([]), [InertiaResolverService::class]);

        $this->assertFalse($resolver->supports($this->param('svc')));
    }

    #[Test]
    public function defaultAllowListRecognisesTheInertiaFactory(): void
    {
        // Constructed without an explicit list, so the InertiaFactory default applies.
        $resolver = new InertiaResolver($this->container([InertiaFactory::class => new stdClass()]));

        $this->assertTrue($resolver->supports($this->param('factory')));
    }

    #[Test]
    public function resolveReturnsTheContainerEntry(): void
    {
        $service = new InertiaResolverService();
        $resolver = new InertiaResolver(
            $this->container([InertiaResolverService::class => $service]),
            [InertiaResolverService::class],
        );

        $this->assertSame($service, $resolver->resolve($this->param('svc'), []));
    }

    private function param(string $name): ReflectionParameter
    {
        foreach ((new ReflectionMethod(InertiaResolverController::class, 'action'))->getParameters() as $param) {
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

final class InertiaResolverService {}

final class InertiaResolverOther {}

final class InertiaResolverController
{
    public function action(InertiaResolverService $svc, int $n, InertiaResolverOther $other, InertiaFactory $factory): void {}
}
