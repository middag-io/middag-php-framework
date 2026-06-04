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
 * Test fixture: a vanilla leaf model over a `widgets` table.
 *
 * @internal
 */
final class Widget extends Model
{
    protected string $table = 'widgets';

    /** @var list<string> */
    protected array $fillable = ['name', 'price', 'active'];

    /** @var array<string, string> */
    protected array $casts = [
        'id' => 'int',
        'price' => 'float',
        'active' => 'bool',
    ];
}
