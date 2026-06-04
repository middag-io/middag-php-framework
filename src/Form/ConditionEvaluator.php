<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form;

use Middag\Ui\Condition\Condition;
use Middag\Ui\Shared\Enum\ConditionOperator;

/**
 * Evaluates a condition against a set of form values.
 *
 * Stateless: each call to evaluate() is independent.
 * All operators from ConditionOperator are handled via a single match expression.
 *
 * @internal
 */
final class ConditionEvaluator
{
    /**
     * Evaluate a condition against the given form values.
     *
     * @param Condition            $cond   the condition to evaluate
     * @param array<string, mixed> $values current form values keyed by field name
     *
     * @return bool true when the condition is satisfied
     */
    public function evaluate(Condition $cond, array $values): bool
    {
        $actual = $values[$cond->field] ?? null;

        return match ($cond->operator) {
            ConditionOperator::EQ => $actual === $cond->value,
            ConditionOperator::NEQ => $actual !== $cond->value,
            ConditionOperator::IN => is_array($cond->value) && in_array($actual, $cond->value, true),
            ConditionOperator::NOT_IN => is_array($cond->value) && !in_array($actual, $cond->value, true),
            ConditionOperator::GT => $actual > $cond->value,
            ConditionOperator::GTE => $actual >= $cond->value,
            ConditionOperator::LT => $actual < $cond->value,
            ConditionOperator::LTE => $actual <= $cond->value,
            ConditionOperator::TRUTHY => (bool) $actual,
            ConditionOperator::FALSY => !$actual,
            ConditionOperator::EXISTS => array_key_exists($cond->field, $values) && $actual !== null,
            ConditionOperator::EMPTY => !array_key_exists($cond->field, $values) || $actual === null || $actual === '' || $actual === [],
            ConditionOperator::MATCHES => is_string($actual) && is_string($cond->value) && @preg_match($cond->value, $actual) === 1,
        };
    }
}
