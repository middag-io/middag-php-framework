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

use Middag\Framework\Http\Contract\FormRequestInterface;
use Middag\Framework\Http\Resolver\FormRequestResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FormRequestResolver::class)]
final class FormRequestResolverTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueForFormRequestSubclass(): void
    {
        $resolver = new FormRequestResolver($this->container([]), Request::create('/'));
        $param = $this->paramByName('controller_method', 'request');

        $this->assertTrue($resolver->supports($param));
    }

    #[Test]
    public function supportsReturnsFalseForNonFormRequestType(): void
    {
        $resolver = new FormRequestResolver($this->container([]), Request::create('/'));
        $param = $this->paramByName('controller_method', 'plain');

        $this->assertFalse($resolver->supports($param));
    }

    #[Test]
    public function resolveUsesContainerWhenBound(): void
    {
        $instance = new FakeFormRequest();
        $resolver = new FormRequestResolver(
            $this->container([FakeFormRequest::class => $instance]),
            Request::create('/')
        );

        $param = $this->paramByName('controller_method', 'request');
        $result = $resolver->resolve($param, []);

        $this->assertSame($instance, $result);
        $this->assertSame(1, $instance->validateCalls);
    }

    #[Test]
    public function resolveFallsBackToNewWhenUnbound(): void
    {
        $resolver = new FormRequestResolver(
            $this->container([]),
            Request::create('/')
        );

        $param = $this->paramByName('controller_method', 'request');
        $result = $resolver->resolve($param, []);

        $this->assertInstanceOf(FakeFormRequest::class, $result);
        $this->assertSame(1, $result->validateCalls);
    }

    private function paramByName(string $method, string $paramName): ReflectionParameter
    {
        foreach ((new ReflectionMethod(FakeFormRequestController::class, $method))->getParameters() as $param) {
            if ($param->getName() === $paramName) {
                return $param;
            }
        }

        throw new RuntimeException('parameter not found');
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

final class FakeFormRequest implements FormRequestInterface
{
    public int $validateCalls = 0;

    public function __construct(?Request $request = null) {}

    public function validate(): void
    {
        ++$this->validateCalls;
    }
}

final class FakeFormRequestController
{
    public function controller_method(FakeFormRequest $request, string $plain = ''): void {}
}
