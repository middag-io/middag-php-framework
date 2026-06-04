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

use Middag\Framework\Kernel\Bootstrap\BootRethrowFailurePolicy;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
#[CoversClass(BootRethrowFailurePolicy::class)]
final class BootRethrowFailurePolicyTest extends TestCase
{
    public function testHandleRethrowsTheSameThrowable(): void
    {
        $policy = new BootRethrowFailurePolicy();
        $module = $this->createStub(ModuleInterface::class);
        $failure = new RuntimeException('boot exploded');

        try {
            $policy->handle($module, $failure);
            self::fail('Expected the failure to be rethrown.');
        } catch (Throwable $throwable) {
            self::assertSame($failure, $throwable);
        }
    }
}
