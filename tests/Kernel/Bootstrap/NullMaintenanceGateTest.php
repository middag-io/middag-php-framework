<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Bootstrap;

use Middag\Framework\Kernel\Bootstrap\NullMaintenanceGate;
use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullMaintenanceGate::class)]
final class NullMaintenanceGateTest extends TestCase
{
    public function testStandaloneIsNeverUnderMaintenance(): void
    {
        $gate = new NullMaintenanceGate();

        self::assertInstanceOf(MaintenanceGateInterface::class, $gate);
        self::assertFalse($gate->isUnderMaintenance());
    }
}
