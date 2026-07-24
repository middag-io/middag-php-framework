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
 * Cast fixture model: mixes a custom cast (price → {@see Money}) with a built-in
 * cast (id → int), so the round-trip test proves the two coexist.
 *
 * @internal
 */
final class Product extends Model
{
    protected string $table = 'products';

    /** @var list<string> */
    protected array $fillable = ['name', 'price'];

    /** @var array<string, string> */
    protected array $casts = ['id' => 'int', 'price' => MoneyCast::class];
}
