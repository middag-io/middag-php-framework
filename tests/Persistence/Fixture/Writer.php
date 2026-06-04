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
use Middag\Framework\Persistence\Relation\HasMany;
use Middag\Framework\Persistence\Relation\HasOne;

/**
 * Relationship fixture: a writer with many books and one bio.
 *
 * @internal
 */
final class Writer extends Model
{
    protected string $table = 'writers';

    /** @var list<string> */
    protected array $fillable = ['name'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int'];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function bio(): HasOne
    {
        return $this->hasOne(Bio::class);
    }
}
