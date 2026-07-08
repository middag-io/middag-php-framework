<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Module;

use Middag\Framework\Kernel\Module\AbstractHookRegister;
use Middag\Framework\Tests\Kernel\Module\Fixture\RecordingHookRegister;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The hook-register base: register() drives registerActions() then
 * registerFilters(), and the default implementations are safe no-ops.
 *
 * @internal
 */
#[CoversClass(AbstractHookRegister::class)]
final class AbstractHookRegisterTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingHookRegister::reset();
    }

    protected function tearDown(): void
    {
        RecordingHookRegister::reset();
    }

    #[Test]
    public function registerRunsActionsThenFilters(): void
    {
        RecordingHookRegister::register();

        self::assertSame(['actions', 'filters'], RecordingHookRegister::$calls);
    }

    #[Test]
    public function defaultRegisterIsASafeNoOp(): void
    {
        $register = new class extends AbstractHookRegister {};

        $register::register();

        $this->expectNotToPerformAssertions();
    }
}
