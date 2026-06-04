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

use Middag\Framework\Persistence\Model;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Persistence\Repository\AbstractRepository;

/**
 * Generic Data-Mapper repository contract.
 *
 * The Data-Mapper counterpart to the Active Record {@see Model}:
 * domain entities stay persistence-ignorant and the repository loads/stores them through a
 * {@see MapperInterface}. The default {@see AbstractRepository}
 * runs on the same Eloquent-like {@see QueryBuilder} engine, so both
 * persistence styles share one connection seam — pick per need.
 *
 * @api
 *
 * @template T of EntityInterface
 */
interface RepositoryInterface
{
    /**
     * Find a single entity by primary key, or null when absent.
     *
     * @return null|T
     */
    public function find(int $id): ?EntityInterface;

    /**
     * Return every entity in the table.
     *
     * @return list<T>
     */
    public function findAll(): array;

    /**
     * Persist an entity — insert when it has no id, update otherwise.
     *
     * @param T $entity
     */
    public function save(EntityInterface $entity): void;

    /**
     * Delete an entity by its primary key. No-op when the entity has no id.
     *
     * @param T $entity
     */
    public function delete(EntityInterface $entity): void;
}
