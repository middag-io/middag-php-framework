<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Attribute;

use Attribute;
use Middag\Framework\Bus\Attribute\Schedule;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversNothing]
final class ScheduleTest extends TestCase
{
    #[Test]
    public function constructorDefaults(): void
    {
        $schedule = new Schedule();

        $this->assertSame('*', $schedule->minute);
        $this->assertSame('*', $schedule->hour);
        $this->assertSame('*', $schedule->day);
        $this->assertSame('*', $schedule->month);
        $this->assertSame('*', $schedule->dayOfWeek);
        $this->assertFalse($schedule->disabled);
        $this->assertFalse($schedule->exclusive);
    }

    #[Test]
    public function constructorWithCustomValues(): void
    {
        $schedule = new Schedule(
            minute: '*/5',
            hour: '3',
            day: '1',
            month: '6',
            dayOfWeek: '0',
            disabled: true,
            exclusive: true,
        );

        $this->assertSame('*/5', $schedule->minute);
        $this->assertSame('3', $schedule->hour);
        $this->assertSame('1', $schedule->day);
        $this->assertSame('6', $schedule->month);
        $this->assertSame('0', $schedule->dayOfWeek);
        $this->assertTrue($schedule->disabled);
        $this->assertTrue($schedule->exclusive);
    }

    #[Test]
    public function constructorSupportsRandomScheduling(): void
    {
        $schedule = new Schedule(minute: 'R', hour: 'R');

        $this->assertSame('R', $schedule->minute);
        $this->assertSame('R', $schedule->hour);
    }

    #[Test]
    public function isAPhpAttribute(): void
    {
        $reflection = new ReflectionClass(Schedule::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
    }

    #[Test]
    public function targetsClassOnly(): void
    {
        $reflection = new ReflectionClass(Schedule::class);
        $attributes = $reflection->getAttributes(Attribute::class);
        $attribute = $attributes[0]->newInstance();

        $this->assertSame(Attribute::TARGET_CLASS, $attribute->flags);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(Schedule::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
