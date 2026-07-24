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
 * Cast fixture value object: money stored as integer cents.
 *
 * @internal
 */
final readonly class Money
{
    public function __construct(public int $cents) {}
}
