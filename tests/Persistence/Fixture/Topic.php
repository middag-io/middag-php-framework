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
use Middag\Framework\Persistence\Relation\BelongsToMany;

/**
 * Relationship fixture: the many-to-many target/inverse of Book::topics().
 *
 * @internal
 */
final class Topic extends Model
{
    protected string $table = 'topics';

    /** @var list<string> */
    protected array $fillable = ['label'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int'];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
