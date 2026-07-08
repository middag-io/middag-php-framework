<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Query;

use InvalidArgumentException;

/**
 * Host-neutral reference to a relation that can be joined by query builders.
 *
 * It intentionally carries relation semantics instead of only raw SQL: callers
 * can keep local/target fields, cardinality and host policy discoverable while
 * builders still compile the final ON expression for their own table aliases.
 *
 * @api
 */
final readonly class RelationRef
{
    public const CARDINALITY_ONE_TO_ONE = 'one-to-one';

    public const CARDINALITY_ONE_TO_MANY = 'one-to-many';

    public const CARDINALITY_MANY_TO_ONE = 'many-to-one';

    public const CARDINALITY_MANY_TO_MANY = 'many-to-many';

    public const HOST_POLICY_AGNOSTIC = 'agnostic';

    public const HOST_POLICY_HOST_TABLE = 'host-table';

    public const HOST_POLICY_SCOPE_TABLE = 'scope-table';

    /**
     * @param array<int, string> $additionalPredicates extra ON predicates, already expressed against the builder aliases
     */
    public function __construct(
        public string $targetTable,
        public string $localField,
        public string $targetField = 'id',
        public ?string $defaultAlias = null,
        public string $cardinality = self::CARDINALITY_MANY_TO_ONE,
        public string $hostPolicy = self::HOST_POLICY_AGNOSTIC,
        public string $joinType = 'INNER',
        public array $additionalPredicates = [],
    ) {
        $this->assertIdentifier($targetTable, 'target table');
        $this->assertIdentifier($localField, 'local field');
        $this->assertIdentifier($targetField, 'target field');

        if ($defaultAlias !== null) {
            $this->assertIdentifier($defaultAlias, 'default alias');
        }

        if (!in_array($cardinality, [
            self::CARDINALITY_ONE_TO_ONE,
            self::CARDINALITY_ONE_TO_MANY,
            self::CARDINALITY_MANY_TO_ONE,
            self::CARDINALITY_MANY_TO_MANY,
        ], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported relation cardinality "%s".', $cardinality));
        }

        if (!in_array($hostPolicy, [
            self::HOST_POLICY_AGNOSTIC,
            self::HOST_POLICY_HOST_TABLE,
            self::HOST_POLICY_SCOPE_TABLE,
        ], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported relation host policy "%s".', $hostPolicy));
        }

        $normalizedJoinType = strtoupper($joinType);
        if (!in_array($normalizedJoinType, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported relation join type "%s".', $joinType));
        }

        foreach ($additionalPredicates as $predicate) {
            if (trim($predicate) === '') {
                throw new InvalidArgumentException('Additional relation predicates must be non-empty strings.');
            }
        }
    }

    public function alias(?string $alias = null): string
    {
        $resolved = $alias ?? $this->defaultAlias ?? $this->targetTable;
        $this->assertIdentifier($resolved, 'alias');

        return $resolved;
    }

    public function normalizedJoinType(): string
    {
        return strtoupper($this->joinType);
    }

    public function onExpression(?string $targetAlias = null, ?string $sourceAlias = null): string
    {
        $target = $this->alias($targetAlias);
        $source = $sourceAlias ?? 'item';
        $this->assertIdentifier($source, 'source alias');

        $predicates = [
            sprintf('%s.%s = %s.%s', $target, $this->targetField, $source, $this->localField),
            ...$this->additionalPredicates,
        ];

        return implode(' AND ', $predicates);
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (trim($value) === '' || !preg_match('/^[A-Za-z_]\w*$/', $value)) {
            throw new InvalidArgumentException(sprintf('Invalid relation %s "%s".', $label, $value));
        }
    }
}
