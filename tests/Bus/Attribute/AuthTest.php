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
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
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
        $this->assertCount(3, $auth->requirements);
        $this->assertContainsOnlyInstancesOf(CapabilityRequirement::class, $auth->requirements);
    }

    #[Test]
    public function capabilitiesAcceptsReferencesWithoutLosingLegacyKeys(): void
    {
        $auth = new Auth(capabilities: [new CapabilityReference('mod/helico:manage', host: 'moodle')]);

        $this->assertSame(['mod/helico:manage'], $auth->capabilities);
        $this->assertSame('moodle', $auth->requirements[0]->reference?->host);
    }

    #[Test]
    public function requirementsAcceptDefinitionObjectsWithOptions(): void
    {
        $definition = new AuthTestCapabilityDefinition();

        $auth = new Auth(requirements: [$definition]);

        $this->assertSame(['local/foo:configure'], $auth->capabilities);
        $this->assertSame($definition, $auth->requirements[0]->definition);
        $this->assertSame([
            'captype' => 'write',
            'contextlevel' => 'course',
        ], $auth->requirements[0]->options);
    }

    #[Test]
    public function requirementsPreserveDefinitionClassStrings(): void
    {
        $auth = new Auth(requirements: [AuthTestCapabilityDefinition::class]);

        $this->assertSame([], $auth->capabilities);
        $this->assertSame(AuthTestCapabilityDefinition::class, $auth->requirements[0]->definitionClass);
    }

    #[Test]
    public function requirementsAcceptNativeCapabilityStrings(): void
    {
        // A bare native host capability (e.g. Moodle's 'moodle/site:config')
        // resolves to a CapabilityReference, not a definition class — so
        // #[Auth(requirements: ['moodle/site:config'])] works alongside class refs.
        $auth = new Auth(requirements: ['moodle/site:config']);

        $this->assertSame(['moodle/site:config'], $auth->capabilities);
        $this->assertSame('moodle/site:config', $auth->requirements[0]->reference?->key);
        $this->assertNull($auth->requirements[0]->definitionClass);
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

/**
 * @internal
 */
final class AuthTestCapabilityDefinition implements CapabilityDefinitionInterface
{
    public function capabilityReference(): CapabilityReference
    {
        return new CapabilityReference('local/foo:configure', host: 'moodle');
    }

    public function capabilityOptions(): array
    {
        return [
            'captype' => 'write',
            'contextlevel' => 'course',
        ];
    }
}
