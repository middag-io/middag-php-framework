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

use Middag\Framework\Http\Contract\MethodArgumentResolverInterface;
use Middag\Framework\Http\Resolver\MethodParameterResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use RuntimeException;

/**
 * Orchestrates the resolver chain: first supporting resolver wins, otherwise a
 * default value is used, otherwise it fails loudly. Reflection is cached.
 *
 * @internal
 */
#[CoversClass(MethodParameterResolver::class)]
final class MethodParameterResolverTest extends TestCase
{
    #[Test]
    public function resolvesEveryArgumentInDeclarationOrder(): void
    {
        $resolver = new MethodParameterResolver([
            $this->resolverFor('name', 'Ada'),
            $this->resolverFor('count', 7),
        ]);

        $arguments = $resolver->resolveArguments(new MethodParameterResolverController(), 'handle', []);

        $this->assertSame(['Ada', 7], $arguments);
    }

    #[Test]
    public function laterResolverWinsWhenEarlierOnesDecline(): void
    {
        $resolver = new MethodParameterResolver([
            $this->resolverFor('nonexistent', 'never'),
            $this->resolverFor('name', 'Ada'),
            $this->resolverFor('count', 9),
        ]);

        $arguments = $resolver->resolveArguments(new MethodParameterResolverController(), 'handle', []);

        $this->assertSame(['Ada', 9], $arguments);
    }

    #[Test]
    public function fallsBackToTheParameterDefaultWhenNoResolverSupportsIt(): void
    {
        // Only $name is resolved; $count (default 3) is filled from its default.
        $resolver = new MethodParameterResolver([$this->resolverFor('name', 'Ada')]);

        $arguments = $resolver->resolveArguments(new MethodParameterResolverController(), 'handle', []);

        $this->assertSame(['Ada', 3], $arguments);
    }

    #[Test]
    public function throwsWhenAnArgumentHasNeitherResolverNorDefault(): void
    {
        $resolver = new MethodParameterResolver([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unable to resolve argument \$required/');

        $resolver->resolveArguments(new MethodParameterResolverController(), 'needsUnresolvable', []);
    }

    #[Test]
    public function returnsAnEmptyListForAParameterlessMethod(): void
    {
        $resolver = new MethodParameterResolver([$this->resolverFor('x', 1)]);

        $this->assertSame([], $resolver->resolveArguments(new MethodParameterResolverController(), 'noArgs', []));
    }

    #[Test]
    public function forwardsRouteParametersToTheResolver(): void
    {
        $recorder = new class implements MethodArgumentResolverInterface {
            /** @var array<string, mixed> */
            public array $seen = [];

            public function supports(ReflectionParameter $parameter): bool
            {
                return true;
            }

            public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
            {
                $this->seen = $routeParams;

                return $parameter->getName();
            }
        };

        $resolver = new MethodParameterResolver([$recorder]);
        $resolver->resolveArguments(new MethodParameterResolverController(), 'handle', ['id' => 5]);

        $this->assertSame(['id' => 5], $recorder->seen);
    }

    #[Test]
    public function reusesCachedReflectionAcrossRepeatedCalls(): void
    {
        $resolver = new MethodParameterResolver([
            $this->resolverFor('name', 'Ada'),
            $this->resolverFor('count', 1),
        ]);
        $controller = new MethodParameterResolverController();

        $first = $resolver->resolveArguments($controller, 'handle', []);
        $second = $resolver->resolveArguments($controller, 'handle', []);

        $this->assertSame($first, $second);
    }

    private function resolverFor(string $parameterName, mixed $value): MethodArgumentResolverInterface
    {
        return new class($parameterName, $value) implements MethodArgumentResolverInterface {
            public function __construct(
                private readonly string $parameterName,
                private readonly mixed $value,
            ) {}

            public function supports(ReflectionParameter $parameter): bool
            {
                return $parameter->getName() === $this->parameterName;
            }

            public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
            {
                return $this->value;
            }
        };
    }
}

final class MethodParameterResolverController
{
    /** @return array<int, mixed> */
    public function handle(string $name, int $count = 3): array
    {
        return [$name, $count];
    }

    public function noArgs(): void {}

    public function needsUnresolvable(string $required): void {}
}
