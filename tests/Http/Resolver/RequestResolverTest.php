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

use Middag\Framework\Http\Resolver\RequestResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Injects the current Symfony Request (or a subclass) into controller methods,
 * ignoring builtins and unrelated objects.
 *
 * @internal
 */
#[CoversClass(RequestResolver::class)]
final class RequestResolverTest extends TestCase
{
    #[Test]
    public function supportsAPlainRequestParameter(): void
    {
        $resolver = new RequestResolver(Request::create('/'));

        $this->assertTrue($resolver->supports($this->param('request')));
    }

    #[Test]
    public function supportsARequestSubclassParameter(): void
    {
        $resolver = new RequestResolver(Request::create('/'));

        $this->assertTrue($resolver->supports($this->param('sub')));
    }

    #[Test]
    public function doesNotSupportABuiltinType(): void
    {
        $resolver = new RequestResolver(Request::create('/'));

        $this->assertFalse($resolver->supports($this->param('n')));
    }

    #[Test]
    public function doesNotSupportAnUnrelatedObjectType(): void
    {
        $resolver = new RequestResolver(Request::create('/'));

        $this->assertFalse($resolver->supports($this->param('other')));
    }

    #[Test]
    public function resolveAlwaysReturnsTheInjectedRequest(): void
    {
        $request = Request::create('/orders');
        $resolver = new RequestResolver($request);

        $this->assertSame($request, $resolver->resolve($this->param('request'), []));
    }

    private function param(string $name): ReflectionParameter
    {
        foreach ((new ReflectionMethod(RequestResolverController::class, 'action'))->getParameters() as $param) {
            if ($param->getName() === $name) {
                return $param;
            }
        }

        throw new RuntimeException('parameter not found: ' . $name);
    }
}

final class RequestResolverOther {}

final class RequestResolverSubRequest extends Request {}

final class RequestResolverController
{
    public function action(Request $request, int $n, RequestResolverOther $other, RequestResolverSubRequest $sub): void {}
}
