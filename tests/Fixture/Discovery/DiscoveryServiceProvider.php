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
    protected const SCAN_DIRS = ['src'];

    protected const ROOT_NAMESPACE = 'Middag\Framework\Tests\Fixture\Discovery\src';
}
