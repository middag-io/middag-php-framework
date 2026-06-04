<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Fixture;

use Middag\Framework\Persistence\Model;
use Middag\Framework\Persistence\ModelQuery;

/**
 * Test fixture: a model exercising auto timestamps, enum + array casts and a
 * local scope.
 *
 * @internal
 */
final class Task extends Model
{
    protected string $table = 'tasks';

    protected bool $timestamps = true;

    /** @var list<string> */
    protected array $fillable = ['title', 'status', 'tags'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'int',
        'status' => Status::class,
        'tags' => 'array',
    ];

    /**
     * @param ModelQuery<self> $query
     */
    public function scopeDone(ModelQuery $query): void
    {
        $query->where('status', Status::Done->value);
    }
}
