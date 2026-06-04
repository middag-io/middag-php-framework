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

/**
 * Relationship fixture: the one-to-one target of Writer::bio().
 *
 * @internal
 */
final class Bio extends Model
{
    protected string $table = 'bios';

    /** @var list<string> */
    protected array $fillable = ['writer_id', 'summary'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int', 'writer_id' => 'int'];
}
