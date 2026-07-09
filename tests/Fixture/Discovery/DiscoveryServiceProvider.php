<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Fixture\Discovery;

use Middag\Framework\Kernel\ServiceProvider;

/**
 * A ServiceProvider bound to the Discovery fixture tree: it scans
 * tests/Fixture/Discovery/src and maps file paths to the fixture namespace,
 * driving the auto-discovery machinery end to end.
 *
 * @internal
 */
final class DiscoveryServiceProvider extends ServiceProvider
{
    // 'does-not-exist' exercises register()'s is_dir() skip branch: an
    // adapter/app declaring a scan dir that was never created (or removed)
    // must not fail discovery for the directories that do exist.
    protected const SCAN_DIRS = ['src', 'does-not-exist'];

    protected const ROOT_NAMESPACE = 'Middag\Framework\Tests\Fixture\Discovery\src';
}
