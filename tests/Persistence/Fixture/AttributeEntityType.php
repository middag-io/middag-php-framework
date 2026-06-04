<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Fixture;

use Middag\Framework\Persistence\Attribute\EntityType;

/**
 * Declares an entity type via the attribute only (no interface).
 *
 * @internal
 */
#[EntityType('widget', 'Widget')]
final class AttributeEntityType {}
