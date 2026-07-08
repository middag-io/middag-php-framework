<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Database\Schema\Fixture;

/**
 * A class with no `#[Table]` attribute — the reader must reject it.
 *
 * @internal
 */
final class PlainClass {}
