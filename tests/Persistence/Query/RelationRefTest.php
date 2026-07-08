<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Query;

use InvalidArgumentException;
use Middag\Framework\Persistence\Query\RelationRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Host-neutral relation reference: constructor validation, accessors, alias
 * resolution and ON-expression compilation.
 *
 * @internal
 */
#[CoversClass(RelationRef::class)]
final class RelationRefTest extends TestCase
{
    #[Test]
    public function exposesConstructorArgumentsAsReadonlyProperties(): void
    {
        $ref = new RelationRef(
            targetTable: 'roles',
            localField: 'role_id',
            targetField: 'uid',
            defaultAlias: 'r',
            cardinality: RelationRef::CARDINALITY_ONE_TO_MANY,
            hostPolicy: RelationRef::HOST_POLICY_SCOPE_TABLE,
            joinType: 'LEFT',
            additionalPredicates: ['r.deleted = 0'],
        );

        self::assertSame('roles', $ref->targetTable);
        self::assertSame('role_id', $ref->localField);
        self::assertSame('uid', $ref->targetField);
        self::assertSame('r', $ref->defaultAlias);
        self::assertSame(RelationRef::CARDINALITY_ONE_TO_MANY, $ref->cardinality);
        self::assertSame(RelationRef::HOST_POLICY_SCOPE_TABLE, $ref->hostPolicy);
        self::assertSame('LEFT', $ref->joinType);
        self::assertSame(['r.deleted = 0'], $ref->additionalPredicates);
    }

    #[Test]
    public function appliesSensibleDefaults(): void
    {
        $ref = new RelationRef(targetTable: 'roles', localField: 'role_id');

        self::assertSame('id', $ref->targetField);
        self::assertNull($ref->defaultAlias);
        self::assertSame(RelationRef::CARDINALITY_MANY_TO_ONE, $ref->cardinality);
        self::assertSame(RelationRef::HOST_POLICY_AGNOSTIC, $ref->hostPolicy);
        self::assertSame('INNER', $ref->joinType);
        self::assertSame([], $ref->additionalPredicates);
    }

    #[Test]
    public function cardinalityConstantsHoldTheExpectedTokens(): void
    {
        self::assertSame('one-to-one', RelationRef::CARDINALITY_ONE_TO_ONE);
        self::assertSame('one-to-many', RelationRef::CARDINALITY_ONE_TO_MANY);
        self::assertSame('many-to-one', RelationRef::CARDINALITY_MANY_TO_ONE);
        self::assertSame('many-to-many', RelationRef::CARDINALITY_MANY_TO_MANY);
    }

    #[Test]
    public function hostPolicyConstantsHoldTheExpectedTokens(): void
    {
        self::assertSame('agnostic', RelationRef::HOST_POLICY_AGNOSTIC);
        self::assertSame('host-table', RelationRef::HOST_POLICY_HOST_TABLE);
        self::assertSame('scope-table', RelationRef::HOST_POLICY_SCOPE_TABLE);
    }

    #[Test]
    public function aliasPrefersExplicitArgument(): void
    {
        $ref = new RelationRef('roles', 'role_id', defaultAlias: 'r');

        self::assertSame('custom', $ref->alias('custom'));
    }

    #[Test]
    public function aliasFallsBackToDefaultAliasThenTargetTable(): void
    {
        self::assertSame('r', (new RelationRef('roles', 'role_id', defaultAlias: 'r'))->alias());
        self::assertSame('roles', (new RelationRef('roles', 'role_id'))->alias());
    }

    #[Test]
    public function normalizedJoinTypeUppercasesTheStoredValue(): void
    {
        $ref = new RelationRef('roles', 'role_id', joinType: 'left');

        self::assertSame('left', $ref->joinType);
        self::assertSame('LEFT', $ref->normalizedJoinType());
    }

    #[Test]
    public function onExpressionUsesDefaultTargetAndItemSource(): void
    {
        $ref = new RelationRef('roles', 'role_id', targetField: 'id', defaultAlias: 'r');

        self::assertSame('r.id = item.role_id', $ref->onExpression());
    }

    #[Test]
    public function onExpressionHonoursExplicitTargetAndSourceAliases(): void
    {
        $ref = new RelationRef('roles', 'role_id', targetField: 'id');

        self::assertSame('t.id = u.role_id', $ref->onExpression('t', 'u'));
    }

    #[Test]
    public function onExpressionAppendsAdditionalPredicates(): void
    {
        $ref = new RelationRef(
            targetTable: 'roles',
            localField: 'role_id',
            defaultAlias: 'r',
            additionalPredicates: ['r.tenant_id = item.tenant_id', 'r.active = 1'],
        );

        self::assertSame(
            'r.id = item.role_id AND r.tenant_id = item.tenant_id AND r.active = 1',
            $ref->onExpression(),
        );
    }

    #[Test]
    public function rejectsInvalidTargetTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles table', 'role_id');
    }

    #[Test]
    public function rejectsEmptyLocalField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles', '  ');
    }

    #[Test]
    public function rejectsInvalidTargetField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles', 'role_id', targetField: '1bad');
    }

    #[Test]
    public function rejectsInvalidDefaultAlias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles', 'role_id', defaultAlias: 'has-dash');
    }

    #[Test]
    public function rejectsUnsupportedCardinality(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles', 'role_id', cardinality: 'sideways');
    }

    #[Test]
    public function rejectsUnsupportedHostPolicy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles', 'role_id', hostPolicy: 'nonsense');
    }

    #[Test]
    public function rejectsUnsupportedJoinType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles', 'role_id', joinType: 'CROSS');
    }

    #[Test]
    public function rejectsBlankAdditionalPredicate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationRef('roles', 'role_id', additionalPredicates: ['   ']);
    }

    #[Test]
    public function aliasRejectsInvalidExplicitIdentifier(): void
    {
        $ref = new RelationRef('roles', 'role_id');

        $this->expectException(InvalidArgumentException::class);
        $ref->alias('bad alias');
    }

    #[Test]
    public function onExpressionRejectsInvalidSourceAlias(): void
    {
        $ref = new RelationRef('roles', 'role_id');

        $this->expectException(InvalidArgumentException::class);
        $ref->onExpression(null, 'bad source');
    }
}
