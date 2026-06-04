<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Relation;

use Middag\Framework\Persistence\Model;
use Middag\Framework\Persistence\ModelQuery;

/**
 * Base for Eloquent-like relationships (OSS, host-agnostic).
 *
 * A relation wraps a query on the RELATED model plus the parent it hangs off.
 * getResults() serves lazy access for one parent; addEagerConstraints()/match()
 * power with()/load() eager loading — one query for a whole batch of parents,
 * then the results matched back onto them, so reads stay O(1) queries per
 * relation instead of N+1.
 *
 * @api
 */
abstract class Relation
{
    /**
     * @param ModelQuery<Model> $query
     */
    public function __construct(
        protected ModelQuery $query,
        protected Model $parent,
    ) {}

    /**
     * Related result(s) for the single parent this relation was built from.
     */
    abstract public function getResults(): mixed;

    /**
     * Constrain the related query to cover a batch of parents (eager loading).
     *
     * @param array<int, Model> $models
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Assign the eager-loaded $results onto their $models under $relation.
     *
     * @param array<int, Model> $models
     * @param array<int, Model> $results
     *
     * @return array<int, Model>
     */
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * Run the (eager-constrained) related query.
     *
     * @return array<int, Model>
     */
    public function getEager(): array
    {
        return $this->query->get();
    }

    /**
     * @return ModelQuery<Model>
     */
    public function getQuery(): ModelQuery
    {
        return $this->query;
    }

    /**
     * Unique non-null values of $key across the given models.
     *
     * @param array<int, Model> $models
     *
     * @return array<int, mixed>
     */
    protected function collectKeys(array $models, string $key): array
    {
        $keys = [];
        foreach ($models as $model) {
            $value = $model->getAttribute($key);
            if ($value !== null) {
                $keys[] = $value;
            }
        }

        return array_values(array_unique($keys));
    }
}
