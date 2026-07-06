<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Fixture\Discovery\src\Contract;

/**
 * Discovery fixture: lives under an IGNORE_DIRS directory ("Contract"), so the
 * scan skips it via the ignore-dir `continue 2` before any suffix check.
 *
 * @internal
 */
final class IgnoredService {}
