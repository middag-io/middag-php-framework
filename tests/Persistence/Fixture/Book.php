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
use Middag\Framework\Persistence\Relation\BelongsTo;
use Middag\Framework\Persistence\Relation\BelongsToMany;

/**
 * Relationship fixture: a book belonging to a writer, tagged with many topics.
 *
 * @internal
 */
final class Book extends Model
{
    protected string $table = 'books';

    /** @var list<string> */
    protected array $fillable = ['writer_id', 'title'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int', 'writer_id' => 'int'];

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }
}
