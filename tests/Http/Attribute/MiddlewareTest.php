<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Attribute;

use Attribute;
use Middag\Framework\Http\Attribute\Middleware;
use Middag\Framework\Tests\Http\Fixture\InnerMiddleware;
use Middag\Framework\Tests\Http\Fixture\OuterMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(Middleware::class)]
final class MiddlewareTest extends TestCase
{
    #[Test]
    public function acceptsNoMiddleware(): void
    {
        $this->assertSame([], (new Middleware())->middleware);
    }

    #[Test]
    public function keepsVariadicClassStringsAsAList(): void
    {
        $attribute = new Middleware(OuterMiddleware::class, InnerMiddleware::class);

        $this->assertSame([OuterMiddleware::class, InnerMiddleware::class], $attribute->middleware);
        $this->assertSame([0, 1], array_keys($attribute->middleware));
    }

    #[Test]
    public function isARepeatableReadonlyAttributeTargetingMethodsAndClasses(): void
    {
        $reflection = new ReflectionClass(Middleware::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(
            Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE,
            $attributes[0]->newInstance()->flags,
        );
    }
}
