<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture\Discovery;

/**
 * Discovery fixture: a plain class with no #[Route] — must be skipped by
 * discovery and never registered in the container.
 *
 * @internal
 */
final class PlainService
{
    public function doThing(): void {}
}
