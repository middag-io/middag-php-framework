<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

use Middag\Framework\Form\Field\EntityPickerField;

/**
 * Lookup of entity types by key.
 *
 * Consumed by {@see EntityPickerField} to resolve
 * a string source into its concrete entity class.
 *
 * @api
 */
interface EntityTypeRegistryInterface
{
    /** Indexes the type by its {@see EntityTypeInterface::getKey()}; re-registering a key overwrites it (last wins). */
    public function register(EntityTypeInterface $type): void;

    /** Returns the type registered under $key, or throws on an unknown key. */
    public function get(string $key): EntityTypeInterface;

    /** Whether a type is registered under $key. */
    public function has(string $key): bool;

    /**
     * All registered types, keyed by type key.
     *
     * @return array<string, EntityTypeInterface>
     */
    public function all(): array;
}
