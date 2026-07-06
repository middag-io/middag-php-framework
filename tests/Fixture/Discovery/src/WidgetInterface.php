<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Fixture\Discovery\src;

/**
 * Discovery fixture: a single project interface, aliased to its lone
 * implementation by ServiceProvider::registerInterfaceAliases().
 *
 * @internal
 */
interface WidgetInterface
{
    public function name(): string;
}
