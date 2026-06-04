<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Attribute;

use Attribute;
use Middag\Framework\Http\Attribute\Auth;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversNothing]
final class AuthTest extends TestCase
{
    #[Test]
    public function constructorDefaults(): void
    {
        $auth = new Auth();

        $this->assertTrue($auth->login);
        $this->assertSame([], $auth->capabilities);
        $this->assertSame('system', $auth->context);
        $this->assertSame(0, $auth->instanceId);
    }

    #[Test]
    public function constructorWithCustomValues(): void
    {
        $auth = new Auth(
            login: false,
            capabilities: ['mod/helico:manage', 'mod/helico:view'],
            context: 'module',
            instanceId: 42,
        );

        $this->assertFalse($auth->login);
        $this->assertSame(['mod/helico:manage', 'mod/helico:view'], $auth->capabilities);
        $this->assertSame('module', $auth->context);
        $this->assertSame(42, $auth->instanceId);
    }

    #[Test]
    public function isAPhpAttribute(): void
    {
        $reflection = new ReflectionClass(Auth::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
    }

    #[Test]
    public function targetsMethodAndClass(): void
    {
        $reflection = new ReflectionClass(Auth::class);
        $attributes = $reflection->getAttributes(Attribute::class);
        $attribute = $attributes[0]->newInstance();

        $this->assertSame(
            Attribute::TARGET_METHOD | Attribute::TARGET_CLASS,
            $attribute->flags,
        );
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(Auth::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function loginDefaultsToTrue(): void
    {
        $auth = new Auth();
        $this->assertTrue($auth->login);
    }

    #[Test]
    public function loginCanBeDisabled(): void
    {
        $auth = new Auth(login: false);
        $this->assertFalse($auth->login);
    }

    #[Test]
    public function capabilitiesAcceptsArrayOfStrings(): void
    {
        $capabilities = ['manage', 'view', 'edit'];
        $auth = new Auth(capabilities: $capabilities);

        $this->assertSame($capabilities, $auth->capabilities);
        $this->assertCount(3, $auth->capabilities);
    }

    #[Test]
    public function contextDefaultsToSystem(): void
    {
        $auth = new Auth();
        $this->assertSame('system', $auth->context);
    }

    #[Test]
    public function instanceIdDefaultsToZero(): void
    {
        $auth = new Auth();
        $this->assertSame(0, $auth->instanceId);
    }
}
