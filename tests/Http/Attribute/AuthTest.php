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
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(Auth::class)]
final class AuthTest extends TestCase
{
    #[Test]
    public function defaultsRequireLoginWithNoCapabilities(): void
    {
        $auth = new Auth();

        $this->assertTrue($auth->login);
        $this->assertSame([], $auth->capabilities);
        $this->assertSame([], $auth->requirements);
        $this->assertSame('system', $auth->context);
        $this->assertSame(0, $auth->instanceId);
    }

    #[Test]
    public function acceptsCustomLegacyValues(): void
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
        $this->assertCount(2, $auth->requirements);
        $this->assertSame('mod/helico:manage', $auth->requirements[0]->key());
    }

    #[Test]
    public function stringCapabilitiesBecomeReferenceRequirements(): void
    {
        $auth = new Auth(capabilities: ['manage', 'view', 'edit']);

        $this->assertCount(3, $auth->requirements);
        $this->assertSame(['manage', 'view', 'edit'], $auth->capabilities);
        $this->assertSame('manage', $auth->requirements[0]->reference?->key);
    }

    #[Test]
    public function capabilityReferencesKeepTheirHostWhileExposingLegacyKeys(): void
    {
        $auth = new Auth(capabilities: [new CapabilityReference('mod/helico:manage', host: 'moodle')]);

        $this->assertSame(['mod/helico:manage'], $auth->capabilities);
        $this->assertSame('moodle', $auth->requirements[0]->reference?->host);
    }

    #[Test]
    public function definitionObjectRequirementsCarryOptionsButNoLegacyKeyDuplicate(): void
    {
        $definition = new AuthHttpTestCapabilityDefinition();

        $auth = new Auth(requirements: [$definition]);

        $this->assertSame(['local/foo:configure'], $auth->capabilities);
        $this->assertSame($definition, $auth->requirements[0]->definition);
        $this->assertSame(['captype' => 'write', 'contextlevel' => 'course'], $auth->requirements[0]->options);
    }

    #[Test]
    public function definitionClassStringRequirementsHaveNoLegacyKey(): void
    {
        $auth = new Auth(requirements: [AuthHttpTestCapabilityDefinition::class]);

        $this->assertSame([], $auth->capabilities);
        $this->assertSame(AuthHttpTestCapabilityDefinition::class, $auth->requirements[0]->definitionClass);
    }

    #[Test]
    public function capabilitiesAndRequirementsAreMergedInOrder(): void
    {
        $auth = new Auth(
            capabilities: ['legacy/one:do'],
            requirements: ['rich/two:do'],
        );

        $this->assertSame(['legacy/one:do', 'rich/two:do'], $auth->capabilities);
        $this->assertCount(2, $auth->requirements);
    }

    #[Test]
    public function isARepeatableFreeReadonlyAttributeTargetingMethodsAndClasses(): void
    {
        $reflection = new ReflectionClass(Auth::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
        $this->assertTrue($reflection->isReadOnly());
        $this->assertSame(
            Attribute::TARGET_METHOD | Attribute::TARGET_CLASS,
            $attributes[0]->newInstance()->flags,
        );
    }
}

/**
 * @internal
 */
final class AuthHttpTestCapabilityDefinition implements CapabilityDefinitionInterface
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
