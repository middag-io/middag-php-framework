<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form;

use Middag\Framework\Form\ConditionEvaluator;
use Middag\Ui\Condition\Condition;
use Middag\Ui\Shared\Enum\ConditionOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every ConditionOperator arm of the evaluator against a set of form
 * values.
 *
 * @internal
 */
#[CoversClass(ConditionEvaluator::class)]
final class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator();
    }

    #[Test]
    public function equalityMatchesStrictly(): void
    {
        self::assertTrue($this->eval(ConditionOperator::Eq, 'status', 'open', ['status' => 'open']));
        self::assertFalse($this->eval(ConditionOperator::Eq, 'status', 'open', ['status' => 'closed']));
    }

    #[Test]
    public function inequalityIsTheStrictNegation(): void
    {
        self::assertTrue($this->eval(ConditionOperator::Neq, 'status', 'open', ['status' => 'closed']));
        self::assertFalse($this->eval(ConditionOperator::Neq, 'status', 'open', ['status' => 'open']));
    }

    #[Test]
    public function inChecksSetMembership(): void
    {
        self::assertTrue($this->eval(ConditionOperator::In, 'role', ['admin', 'editor'], ['role' => 'editor']));
        self::assertFalse($this->eval(ConditionOperator::In, 'role', ['admin', 'editor'], ['role' => 'guest']));
        // Non-array condition value can never be satisfied.
        self::assertFalse($this->eval(ConditionOperator::In, 'role', 'admin', ['role' => 'admin']));
    }

    #[Test]
    public function notInIsTheComplementOfIn(): void
    {
        self::assertTrue($this->eval(ConditionOperator::NotIn, 'role', ['admin'], ['role' => 'guest']));
        self::assertFalse($this->eval(ConditionOperator::NotIn, 'role', ['admin'], ['role' => 'admin']));
        // Non-array condition value: also unsatisfiable.
        self::assertFalse($this->eval(ConditionOperator::NotIn, 'role', 'admin', ['role' => 'guest']));
    }

    #[Test]
    public function numericComparisons(): void
    {
        self::assertTrue($this->eval(ConditionOperator::Gt, 'age', 18, ['age' => 21]));
        self::assertFalse($this->eval(ConditionOperator::Gt, 'age', 18, ['age' => 18]));

        self::assertTrue($this->eval(ConditionOperator::Gte, 'age', 18, ['age' => 18]));
        self::assertFalse($this->eval(ConditionOperator::Gte, 'age', 18, ['age' => 17]));

        self::assertTrue($this->eval(ConditionOperator::Lt, 'age', 18, ['age' => 17]));
        self::assertFalse($this->eval(ConditionOperator::Lt, 'age', 18, ['age' => 18]));

        self::assertTrue($this->eval(ConditionOperator::Lte, 'age', 18, ['age' => 18]));
        self::assertFalse($this->eval(ConditionOperator::Lte, 'age', 18, ['age' => 19]));
    }

    #[Test]
    public function truthyAndFalsy(): void
    {
        self::assertTrue($this->eval(ConditionOperator::Truthy, 'agree', null, ['agree' => '1']));
        self::assertFalse($this->eval(ConditionOperator::Truthy, 'agree', null, ['agree' => '']));

        self::assertTrue($this->eval(ConditionOperator::Falsy, 'agree', null, ['agree' => 0]));
        self::assertFalse($this->eval(ConditionOperator::Falsy, 'agree', null, ['agree' => 'yes']));
    }

    #[Test]
    public function existsRequiresPresentNonNullKey(): void
    {
        self::assertTrue($this->eval(ConditionOperator::Exists, 'note', null, ['note' => '']));
        self::assertFalse($this->eval(ConditionOperator::Exists, 'note', null, ['note' => null]));
        self::assertFalse($this->eval(ConditionOperator::Exists, 'note', null, []));
    }

    #[Test]
    public function emptyMatchesMissingNullBlankOrEmptyArray(): void
    {
        self::assertTrue($this->eval(ConditionOperator::Empty, 'note', null, []));
        self::assertTrue($this->eval(ConditionOperator::Empty, 'note', null, ['note' => null]));
        self::assertTrue($this->eval(ConditionOperator::Empty, 'note', null, ['note' => '']));
        self::assertTrue($this->eval(ConditionOperator::Empty, 'note', null, ['note' => []]));
        self::assertFalse($this->eval(ConditionOperator::Empty, 'note', null, ['note' => 'x']));
    }

    #[Test]
    public function matchesAppliesARegularExpression(): void
    {
        self::assertTrue($this->eval(ConditionOperator::Matches, 'code', '/^AB\d+$/', ['code' => 'AB123']));
        self::assertFalse($this->eval(ConditionOperator::Matches, 'code', '/^AB\d+$/', ['code' => 'XY123']));
        // Non-string actual value never matches.
        self::assertFalse($this->eval(ConditionOperator::Matches, 'code', '/^\d+$/', ['code' => 123]));
        // Invalid pattern is suppressed and treated as no match.
        self::assertFalse($this->eval(ConditionOperator::Matches, 'code', 'not-a-regex', ['code' => 'AB']));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function eval(ConditionOperator $operator, string $field, mixed $value, array $values): bool
    {
        return $this->evaluator->evaluate(new Condition($field, $operator, $value), $values);
    }
}
