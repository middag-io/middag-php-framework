<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence;

use Middag\Framework\Kernel\ServiceProvider;
use Middag\Framework\Persistence\Contract\EntityTypeRegistryInterface;
use Middag\Framework\Persistence\Entity\DefaultEntityType;
use Middag\Framework\Persistence\Entity\EntityTypeRegistry;
use Middag\Framework\Persistence\Loader\EntityTypeRegistrar;
use Middag\Framework\Tests\Persistence\Fixture\AbstractEntityType;
use Middag\Framework\Tests\Persistence\Fixture\AttributeEntityType;
use Middag\Framework\Tests\Persistence\Fixture\InterfaceEntityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The standalone entity-type registrar populates the registry from both
 * attribute-declared and interface-implemented types, skipping everything else.
 *
 * @internal
 */
#[CoversClass(EntityTypeRegistrar::class)]
#[CoversClass(DefaultEntityType::class)]
#[CoversClass(EntityTypeRegistry::class)]
#[CoversClass(ServiceProvider::class)]
final class EntityTypeRegistrarTest extends TestCase
{
    public function testRegistersAttributeAndInterfaceTypesSkippingOthers(): void
    {
        $registry = new EntityTypeRegistry();

        (new EntityTypeRegistrar($registry))->register([
            AttributeEntityType::class,
            InterfaceEntityType::class,
            stdClass::class,
        ]);

        self::assertCount(2, $registry->all());
        self::assertTrue($registry->has('widget'));
        self::assertTrue($registry->has('gadget'));
        self::assertSame(AttributeEntityType::class, $registry->get('widget')->getEntityClass());
        self::assertSame('Widget', $registry->get('widget')->getLabel());
        self::assertSame('Gadget', $registry->get('gadget')->getLabel());
    }

    public function testRegisterSkipsNonInstantiableEntityTypes(): void
    {
        $registry = new EntityTypeRegistry();

        // Implements the contract but is abstract → not instantiable → skipped.
        (new EntityTypeRegistrar($registry))->register([AbstractEntityType::class]);

        self::assertCount(0, $registry->all());
    }

    public function testDefaultEntityTypeLabelFallsBackToKey(): void
    {
        $type = new DefaultEntityType('thing', AttributeEntityType::class);

        self::assertSame('thing', $type->getKey());
        self::assertSame('thing', $type->getLabel());
        self::assertSame(AttributeEntityType::class, $type->getEntityClass());
    }

    public function testWiredContainerResolvesRegistrarAndRegistry(): void
    {
        $container = new ContainerBuilder();
        ServiceProvider::register($container, __DIR__);
        $container->compile();

        self::assertInstanceOf(EntityTypeRegistrar::class, $container->get(EntityTypeRegistrar::class));
        self::assertInstanceOf(EntityTypeRegistryInterface::class, $container->get(EntityTypeRegistryInterface::class));
    }
}
