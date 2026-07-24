<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

use Middag\Framework\Persistence\Model;

/**
 * A consumer-defined attribute cast: the extensible seam beyond the fixed
 * built-in cast list (int/float/bool/string/datetime/array/json/backed-enum).
 *
 * Register a cast by its FQCN in a model's `$casts` map — e.g.
 * `protected array $casts = ['price' => MoneyCast::class];` — and the model
 * routes reads through {@see get()} (DB scalar → rich PHP value) and writes
 * through {@see set()} (rich PHP value → DB-storable scalar). This is how
 * value objects (Money, Email, CPF) get a declarative round-trip without
 * relying on the built-in list.
 *
 * A cast is instantiated once per model class and reused; keep implementations
 * stateless. Instantiation prefers a bound PSR container
 * ({@see Model::setCastResolver()}) and falls
 * back to `new` — so a cast may declare constructor dependencies when a
 * container is wired, or none at all for standalone use.
 *
 * @api
 */
interface CastInterface
{
    /**
     * Transform the raw DB value into its PHP representation on read.
     *
     * Called only for a non-null attribute (null passes through uncast). Return
     * the rich value the application should see.
     */
    public function get(mixed $value): mixed;

    /**
     * Transform the PHP value into its DB-storable scalar on write.
     *
     * Called only for a non-null attribute (null passes through uncast). Return
     * a value the connection adapter can bind (scalar or JSON string).
     */
    public function set(mixed $value): mixed;
}
