<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Loader;

use Middag\Framework\Persistence\Attribute\EntityType;
use Middag\Framework\Persistence\Contract\EntityTypeInterface;
use Middag\Framework\Persistence\Contract\EntityTypeRegistryInterface;
use Middag\Framework\Persistence\Entity\DefaultEntityType;
use ReflectionClass;

/**
 * Populates the {@see EntityTypeRegistryInterface} standalone from a set of
 * classes — no adapter or core registrar required.
 *
 * Call {@see self::register()} at boot with the candidate classes (e.g. those
 * discovered by the app). For each: a class implementing
 * {@see EntityTypeInterface} is registered directly (when it is no-arg
 * constructible); a class merely carrying `#[EntityType]` is wrapped in a
 * {@see DefaultEntityType} built from the attribute's key/label and the class
 * itself as the entity class. Anything else is ignored.
 *
 * Adapters/core that own their own registry (e.g. Moodle item types) populate
 * it their own way and need not use this registrar.
 *
 * @api
 */
final readonly class EntityTypeRegistrar
{
    public function __construct(private EntityTypeRegistryInterface $registry) {}

    /**
     * @param iterable<class-string> $classes
     */
    public function register(iterable $classes): void
    {
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->implementsInterface(EntityTypeInterface::class)) {
                if ($this->isNoArgInstantiable($reflection)) {
                    /** @var EntityTypeInterface $type */
                    $type = $reflection->newInstance();
                    $this->registry->register($type);
                }

                continue;
            }

            $attributes = $reflection->getAttributes(EntityType::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $this->registry->register(new DefaultEntityType($attribute->key, $class, $attribute->label));
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function isNoArgInstantiable(ReflectionClass $reflection): bool
    {
        if (!$reflection->isInstantiable()) {
            return false;
        }

        $constructor = $reflection->getConstructor();

        return $constructor === null || $constructor->getNumberOfRequiredParameters() === 0;
    }
}
