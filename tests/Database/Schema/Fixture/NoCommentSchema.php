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

use Middag\Framework\Database\Attribute\Column;
use Middag\Framework\Database\Attribute\Table;

/**
 * A table with no comment and no keys/indexes — proves the descriptor omits
 * `comment` and still emits empty `keys`/`indexes` arrays.
 *
 * @internal
 */
#[Table('nc_table')]
#[Column('id', 'int', length: 10, notnull: true, sequence: true)]
final class NoCommentSchema {}
