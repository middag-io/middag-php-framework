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

use Middag\Framework\Http\Resolver\RouteParameterResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

/**
 * Fallback resolver for route scalars: claims untyped and builtin parameters,
 * casts by the declared scalar type, and falls back to defaults.
 *
 * @internal
 */
#[CoversClass(RouteParameterResolver::class)]
final class RouteParameterResolverTest extends TestCase
{
    #[Test]
    public function supportsAnUntypedParameter(): void
    {
        $this->assertTrue((new RouteParameterResolver())->supports($this->param('scalars', 'untyped')));
    }

    #[Test]
    public function supportsABuiltinScalarParameter(): void
    {
        $this->assertTrue((new RouteParameterResolver())->supports($this->param('scalars', 'id')));
    }

    #[Test]
    public function doesNotSupportAnObjectParameter(): void
    {
        $this->assertFalse((new RouteParameterResolver())->supports($this->param('service', 'svc')));
    }

    #[Test]
    public function doesNotSupportAUnionTypeParameter(): void
    {
        $this->assertFalse((new RouteParameterResolver())->supports($this->param('withUnion', 'mixed')));
    }

    #[Test]
    public function castsMatchedRouteValuesToTheDeclaredScalarType(): void
    {
        $resolver = new RouteParameterResolver();

        $this->assertSame(42, $resolver->resolve($this->param('scalars', 'id'), ['id' => '42']));
        $this->assertTrue($resolver->resolve($this->param('scalars', 'active'), ['active' => '1']));
        $this->assertFalse($resolver->resolve($this->param('scalars', 'active'), ['active' => '0']));
        $this->assertSame(3.5, $resolver->resolve($this->param('scalars', 'ratio'), ['ratio' => '3.5']));
        $this->assertSame('hello', $resolver->resolve($this->param('scalars', 'slug'), ['slug' => 'hello']));
    }

    #[Test]
    public function returnsUntypedRouteValuesVerbatim(): void
    {
        $resolver = new RouteParameterResolver();

        $this->assertSame('raw', $resolver->resolve($this->param('scalars', 'untyped'), ['untyped' => 'raw']));
    }

    #[Test]
    public function returnsUnionTypedRouteValuesVerbatim(): void
    {
        $resolver = new RouteParameterResolver();

        // A non-named (union) type skips the scalar-cast match arm entirely.
        $this->assertSame('7', $resolver->resolve($this->param('withUnion', 'mixed'), ['mixed' => '7']));
    }

    #[Test]
    public function fallsBackToTheDefaultWhenTheRouteValueIsMissing(): void
    {
        $resolver = new RouteParameterResolver();

        $this->assertSame(1, $resolver->resolve($this->param('withDefault', 'page'), []));
    }

    #[Test]
    public function throwsWhenTheRouteValueIsMissingAndNoDefaultExists(): void
    {
        $resolver = new RouteParameterResolver();

        $this->expectException(RuntimeException::class);

        $resolver->resolve($this->param('scalars', 'id'), []);
    }

    private function param(string $method, string $name): ReflectionParameter
    {
        foreach ((new ReflectionMethod(RouteParameterResolverController::class, $method))->getParameters() as $param) {
            if ($param->getName() === $name) {
                return $param;
            }
        }

        throw new RuntimeException('parameter not found: ' . $name);
    }
}

final class RouteParameterResolverService {}

final class RouteParameterResolverController
{
    public function scalars(int $id, bool $active, float $ratio, string $slug, $untyped): void {}

    public function withUnion(int|string $mixed): void {}

    public function withDefault(int $page = 1): void {}

    public function service(RouteParameterResolverService $svc): void {}
}
