<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Enum;

use Middag\Framework\Shared\Enum\Operator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Operator::class)]
final class OperatorTest extends TestCase
{
    #[Test]
    public function allExpectedOperatorsExist(): void
    {
        $values = array_column(Operator::cases(), 'value');

        $this->assertContains('=', $values);
        $this->assertContains('<>', $values);
        $this->assertContains('>', $values);
        $this->assertContains('>=', $values);
        $this->assertContains('<', $values);
        $this->assertContains('<=', $values);
        $this->assertContains('LIKE', $values);
        $this->assertContains('IN', $values);
        $this->assertContains('NOT IN', $values);
        $this->assertContains('BETWEEN', $values);
        $this->assertContains('IS', $values);
        $this->assertContains('IS NOT', $values);
        $this->assertContains('RAW', $values);
    }

    #[Test]
    public function sqlMethodReturnsValue(): void
    {
        $this->assertSame('=', Operator::EQ->sql());
        $this->assertSame('<>', Operator::NEQ->sql());
        $this->assertSame('LIKE', Operator::LIKE->sql());
        $this->assertSame('NOT IN', Operator::NOT_IN->sql());
        $this->assertSame('BETWEEN', Operator::BETWEEN->sql());
        $this->assertSame('IS NOT', Operator::IS_NOT->sql());
    }

    #[Test]
    public function totalOperatorCount(): void
    {
        $this->assertCount(13, Operator::cases());
    }
}
