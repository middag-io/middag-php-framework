<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Module\Fixture;

use Middag\Framework\Kernel\Module\AbstractHookRegister;

/**
 * Concrete hook register that records the order in which register() drives
 * registerActions() then registerFilters().
 *
 * @internal
 */
final class RecordingHookRegister extends AbstractHookRegister
{
    /** @var list<string> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    protected static function registerActions(): void
    {
        self::$calls[] = 'actions';
    }

    protected static function registerFilters(): void
    {
        self::$calls[] = 'filters';
    }
}
