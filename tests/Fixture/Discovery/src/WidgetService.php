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
 * Discovery fixture: a concrete `Service` with a public constructor that
 * implements exactly one project interface — the happy path of
 * scanDirectory() + registerInterfaceAliases().
 *
 * @internal
 */
final class WidgetService implements WidgetInterface
{
    public function name(): string
    {
        return 'widget';
    }
}
