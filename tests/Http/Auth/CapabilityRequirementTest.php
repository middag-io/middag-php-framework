<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Auth;

use InvalidArgumentException;
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CapabilityRequirement::class)]
final class CapabilityRequirementTest extends TestCase
{
    #[Test]
    public function constructorRejectsAnEmptyRequirement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CapabilityRequirement();
    }

    #[Test]
    public function constructorAcceptsAReferenceOnly(): void
    {
        $requirement = new CapabilityRequirement(reference: new CapabilityReference('local/foo:view'));

        $this->assertSame('local/foo:view', $requirement->key());
        $this->assertNull($requirement->definitionClass);
        $this->assertNull($requirement->definition);
    }

    #[Test]
    public function constructorAcceptsADefinitionClassOnly(): void
    {
        $requirement = new CapabilityRequirement(definitionClass: CapabilityRequirementTestDefinition::class);

        $this->assertSame(CapabilityRequirementTestDefinition::class, $requirement->definitionClass);
        $this->assertNull($requirement->key());
    }

    #[Test]
    public function constructorAcceptsADefinitionObjectOnly(): void
    {
        $definition = new CapabilityRequirementTestDefinition();
        $requirement = new CapabilityRequirement(definition: $definition);

        $this->assertSame($definition, $requirement->definition);
    }

    #[Test]
    public function fromReturnsTheSameInstanceWhenNoOptionsGiven(): void
    {
        $original = CapabilityRequirement::from('local/foo:view');

        $this->assertSame($original, CapabilityRequirement::from($original));
    }

    #[Test]
    public function fromMergesOptionsOntoAnExistingRequirement(): void
    {
        $original = new CapabilityRequirement(
            reference: new CapabilityReference('local/foo:view'),
            options: ['contextlevel' => 'system', 'captype' => 'read'],
        );

        $merged = CapabilityRequirement::from($original, ['captype' => 'write']);

        $this->assertNotSame($original, $merged);
        $this->assertSame(['contextlevel' => 'system', 'captype' => 'write'], $merged->options);
    }

    #[Test]
    public function fromWrapsABareCapabilityReference(): void
    {
        $reference = new CapabilityReference('mod/quiz:attempt', host: 'moodle');

        $requirement = CapabilityRequirement::from($reference, ['scope' => 'course']);

        $this->assertSame($reference, $requirement->reference);
        $this->assertSame(['scope' => 'course'], $requirement->options);
    }

    #[Test]
    public function fromExpandsADefinitionObjectAndMergesItsOptions(): void
    {
        $definition = new CapabilityRequirementTestDefinition();

        $requirement = CapabilityRequirement::from($definition, ['captype' => 'read']);

        $this->assertSame($definition, $requirement->definition);
        $this->assertSame('local/foo:configure', $requirement->key());
        // Definition options are the base; the passed options win on conflict.
        $this->assertSame(['captype' => 'read', 'contextlevel' => 'course'], $requirement->options);
    }

    #[Test]
    public function fromKeepsADefinitionClassString(): void
    {
        $requirement = CapabilityRequirement::from(CapabilityRequirementTestDefinition::class);

        $this->assertSame(CapabilityRequirementTestDefinition::class, $requirement->definitionClass);
        $this->assertNull($requirement->reference);
        $this->assertNull($requirement->key());
    }

    #[Test]
    public function fromTreatsABareStringAsAReferenceKey(): void
    {
        $requirement = CapabilityRequirement::from('moodle/site:config');

        $this->assertSame('moodle/site:config', $requirement->key());
        $this->assertNull($requirement->definitionClass);
        $this->assertNull($requirement->definition);
    }

    #[Test]
    public function listFromNormalisesAMixedIterable(): void
    {
        $requirements = CapabilityRequirement::listFrom([
            'moodle/site:config',
            new CapabilityReference('local/foo:view'),
            CapabilityRequirementTestDefinition::class,
        ]);

        $this->assertCount(3, $requirements);
        $this->assertSame(['moodle/site:config', 'local/foo:view', null], array_map(
            static fn (CapabilityRequirement $requirement): ?string => $requirement->key(),
            $requirements,
        ));
    }

    #[Test]
    public function legacyCapabilityMirrorsTheReferenceKey(): void
    {
        $withReference = CapabilityRequirement::from('local/foo:view');
        $this->assertSame('local/foo:view', $withReference->legacyCapability());

        $classOnly = CapabilityRequirement::from(CapabilityRequirementTestDefinition::class);
        $this->assertNull($classOnly->legacyCapability());
    }
}

/**
 * @internal
 */
final class CapabilityRequirementTestDefinition implements CapabilityDefinitionInterface
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
