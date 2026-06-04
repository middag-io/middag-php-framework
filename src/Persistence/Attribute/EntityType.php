<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Attribute;

use Attribute;
use Middag\Framework\Persistence\Contract\EntityTypeInterface;
use Middag\Framework\Persistence\Entity\EntityTypeRegistry;

/**
 * Declares a generic entity type on a plain class, without implementing
 * {@see EntityTypeInterface}.
 *
 * Discovery: a standalone app registers the carrying class at boot via the
 * EntityTypeRegistrar, which reads this attribute and populates the
 * {@see EntityTypeRegistry} (the `key` here becomes the registry key, the
 * optional `label` the UI label). A host adapter may instead discover the same
 * attribute through its own service loader.
 *
 * Use this for entities in standalone or host-agnostic apps.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class EntityType
{
    public function __construct(
        public string $key,
        public ?string $label = null,
    ) {}
}
