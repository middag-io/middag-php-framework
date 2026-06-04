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
 * One-to-many: a parent owns many related rows keyed by a foreign key
 * (e.g. Writer hasMany Book on books.writer_id = writers.id).
 *
 * @api
 */
class HasMany extends Relation
{
    /**
     * @param ModelQuery<Model> $query
     */
    public function __construct(
        ModelQuery $query,
        Model $parent,
        protected string $foreignKey,
        protected string $localKey,
    ) {
        parent::__construct($query, $parent);
    }

    /**
     * @return array<int, Model>
     */
    public function getResults(): array
    {
        $key = $this->parent->getAttribute($this->localKey);

        if ($key === null) {
            return [];
        }

        return $this->query->where($this->foreignKey, $key)->get();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn($this->foreignKey, $this->collectKeys($models, $this->localKey));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $grouped = [];
        foreach ($results as $result) {
            $grouped[(string) $result->getAttribute($this->foreignKey)][] = $result;
        }

        foreach ($models as $model) {
            $model->setRelation($relation, $grouped[(string) $model->getAttribute($this->localKey)] ?? []);
        }

        return $models;
    }
}
