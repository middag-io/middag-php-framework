<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

use Middag\Framework\Shared\Enum\Operator;

/**
 * Compiles a single SQL condition (column + operator + value(s)) into a SQL
 * fragment and its named parameters.
 *
 * Platform-agnostic seam so query builders can assemble WHERE/JOIN clauses
 * without depending on a concrete SQL dialect. A host binds a concrete compiler
 * that decides the placeholder/collation strategy; the returned fragment uses
 * named placeholders keyed by the supplied prefix. The signature depends only on
 * the framework {@see Operator} enum and scalars, so it carries no host coupling.
 *
 * @api
 */
interface ConditionCompilerInterface
{
    /**
     * @param string   $column      Fully-qualified column reference (e.g. `item.status`)
     * @param Operator $op          Comparison operator
     * @param mixed    $value       Primary value (may be null for IS / IS NOT NULL)
     * @param mixed    $value2      Secondary value (e.g. BETWEEN upper bound)
     * @param string   $paramPrefix Prefix used to name the emitted placeholders
     *
     * @return array{0: string, 1: array<string, mixed>} [SQL fragment, named parameters]
     */
    public function compileCondition(string $column, Operator $op, mixed $value, mixed $value2, string $paramPrefix): array;
}
