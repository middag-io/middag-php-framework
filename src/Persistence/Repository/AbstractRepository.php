<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Repository;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\Framework\Persistence\Contract\RepositoryInterface;
use Middag\Framework\Persistence\Query\QueryBuilder;

/**
 * Concrete Data-Mapper repository base, powered by the Eloquent-like QueryBuilder.
 *
 * Subclasses declare a table() and a mapper(); they inherit find/findAll/save/delete for free
 * and may add custom finders via the protected query() builder. Entities stay persistence-
 * ignorant — hydration/dehydration goes through the {@see MapperInterface}.
 *
 * Note: on insert, the database-generated id is NOT written back onto a persistence-ignorant
 * entity (no setter is assumed). Re-fetch via find() when the new id is needed.
 *
 * @api
 *
 * @template T of EntityInterface
 *
 * @implements RepositoryInterface<T>
 */
abstract class AbstractRepository implements RepositoryInterface
{
    public function __construct(protected readonly ConnectionAdapterInterface $connection) {}

    /**
     * @return null|T
     */
    public function find(int $id): ?EntityInterface
    {
        $row = $this->query()->where($this->primaryKey(), $id)->first();

        return $row === null ? null : $this->mapper()->dbToDomain($row, []);
    }

    /**
     * @return list<T>
     */
    public function findAll(): array
    {
        $entities = [];

        foreach ($this->query()->get() as $row) {
            $entities[] = $this->mapper()->dbToDomain($row, []);
        }

        return $entities;
    }

    /**
     * @param EntityInterface $entity
     */
    public function save(EntityInterface $entity): void
    {
        $record = $this->mapper()->domainToDb($entity);

        if ($entity->getId() === null) {
            $this->connection->insert($this->table(), $record);

            return;
        }

        $this->query()->where($this->primaryKey(), $entity->getId())->update($record);
    }

    /**
     * @param EntityInterface $entity
     */
    public function delete(EntityInterface $entity): void
    {
        $id = $entity->getId();

        if ($id !== null) {
            $this->query()->where($this->primaryKey(), $id)->delete();
        }
    }

    /**
     * The table this repository maps to.
     */
    abstract protected function table(): string;

    /**
     * The mapper that hydrates/dehydrates this repository's entity.
     *
     * @return MapperInterface<T>
     */
    abstract protected function mapper(): MapperInterface;

    /**
     * Primary key column. Override for a non-'id' key.
     */
    protected function primaryKey(): string
    {
        return 'id';
    }

    /**
     * A fresh Eloquent-like query builder bound to this repository's table.
     *
     * Use it to build custom finders that hydrate via {@see mapper()}.
     */
    protected function query(): QueryBuilder
    {
        return QueryBuilder::on($this->connection, $this->table());
    }
}
