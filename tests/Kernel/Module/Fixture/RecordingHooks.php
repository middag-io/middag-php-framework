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

use Middag\Framework\Kernel\Contract\HookRegisterInterface;

/**
 * Hook-register fixture.
 *
 * Records static register() invocations so AbstractModuleTest can assert the
 * discover-and-dispatch path runs. register() is static (per the contract), so
 * the call count lives in a static counter — reset it in the test setUp().
 *
 * @internal
 */
final class RecordingHooks implements HookRegisterInterface
{
    public static int $calls = 0;

    public static function register(): void
    {
        ++self::$calls;
    }
}
