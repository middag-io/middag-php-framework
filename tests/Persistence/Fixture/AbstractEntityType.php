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

use Middag\Framework\Persistence\Contract\EntityTypeInterface;

/**
 * Test fixture: an abstract EntityType. It implements the contract but is not
 * instantiable, so the registrar must skip it (no-arg-instantiable guard).
 *
 * @internal
 */
abstract class AbstractEntityType implements EntityTypeInterface {}
