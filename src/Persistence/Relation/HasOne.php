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
 * One-to-one: like {@see HasMany} but resolves to a single related row or null
 * (e.g. Writer hasOne Bio on bios.writer_id = writers.id).
 *
 * @api
 */
class HasOne extends Relation
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

    public function getResults(): ?Model
    {
        $key = $this->parent->getAttribute($this->localKey);

        if ($key === null) {
            return null;
        }

        return $this->query->where($this->foreignKey, $key)->first();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn($this->foreignKey, $this->collectKeys($models, $this->localKey));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $map = [];
        foreach ($results as $result) {
            $map[(string) $result->getAttribute($this->foreignKey)] ??= $result;
        }

        foreach ($models as $model) {
            $model->setRelation($relation, $map[(string) $model->getAttribute($this->localKey)] ?? null);
        }

        return $models;
    }
}
