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
 * Many-to-many through a pivot table (e.g. Book belongsToMany Topic via the
 * book_topic pivot). The related query joins the pivot and also selects the
 * parent-side pivot key so eager-loaded rows can be grouped back per parent.
 *
 * @api
 */
class BelongsToMany extends Relation
{
    /**
     * @param ModelQuery<Model> $query
     */
    public function __construct(
        ModelQuery $query,
        Model $parent,
        protected string $table,
        protected string $foreignPivotKey,
        protected string $relatedPivotKey,
        protected string $parentKey,
        protected string $relatedKey,
    ) {
        parent::__construct($query, $parent);
    }

    /**
     * @return array<int, Model>
     */
    public function getResults(): array
    {
        $key = $this->parent->getAttribute($this->parentKey);

        if ($key === null) {
            return [];
        }

        return $this->baseQuery()->where($this->qualifiedForeignPivotKey(), $key)->get();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->baseQuery()->whereIn($this->qualifiedForeignPivotKey(), $this->collectKeys($models, $this->parentKey));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $grouped = [];
        foreach ($results as $result) {
            $grouped[(string) $result->getAttribute($this->foreignPivotKey)][] = $result;
        }

        foreach ($models as $model) {
            $model->setRelation($relation, $grouped[(string) $model->getAttribute($this->parentKey)] ?? []);
        }

        return $models;
    }

    /**
     * Join the pivot to the related table and select the related columns plus
     * the parent-side pivot key (used to group eager results).
     *
     * @return ModelQuery<Model>
     */
    private function baseQuery(): ModelQuery
    {
        $relatedTable = $this->query->getModel()->getTable();

        return $this->query
            ->join(
                $this->table,
                $this->table . '.' . $this->relatedPivotKey,
                '=',
                $relatedTable . '.' . $this->relatedKey,
            )
            ->select($relatedTable . '.*', $this->qualifiedForeignPivotKey());
    }

    private function qualifiedForeignPivotKey(): string
    {
        return $this->table . '.' . $this->foreignPivotKey;
    }
}
