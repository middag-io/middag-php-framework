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

/**
 * Test fixture: a backed enum used to exercise enum attribute casts.
 *
 * @internal
 */
enum Status: string
{
    case Pending = 'pending';

    case Done = 'done';
}
