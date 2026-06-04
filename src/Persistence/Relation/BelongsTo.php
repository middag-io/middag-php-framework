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
 * Inverse one-to-(one|many): the child holds the foreign key pointing at the
 * owner (e.g. Book belongsTo Writer on books.writer_id = writers.id).
 *
 * @api
 */
class BelongsTo extends Relation
{
    /**
     * @param ModelQuery<Model> $query
     */
    public function __construct(
        ModelQuery $query,
        Model $child,
        protected string $foreignKey,
        protected string $ownerKey,
    ) {
        parent::__construct($query, $child);
    }

    public function getResults(): ?Model
    {
        $foreign = $this->parent->getAttribute($this->foreignKey);

        if ($foreign === null) {
            return null;
        }

        return $this->query->where($this->ownerKey, $foreign)->first();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn($this->ownerKey, $this->collectKeys($models, $this->foreignKey));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $map = [];
        foreach ($results as $result) {
            $map[(string) $result->getAttribute($this->ownerKey)] = $result;
        }

        foreach ($models as $model) {
            $foreign = $model->getAttribute($this->foreignKey);
            $model->setRelation($relation, $foreign === null ? null : ($map[(string) $foreign] ?? null));
        }

        return $models;
    }
}
