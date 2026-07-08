<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database\Attribute;

use Attribute;

/**
 * Declares a secondary index on a {@see Table}-annotated schema class.
 *
 * Repeatable at class level. Unique constraints that are not keys are modelled
 * as indexes with `$unique = true` (mirroring the descriptor convention);
 * primary and foreign keys use {@see Key} instead.
 *
 * ```php
 * #[Index('typestatus', ['type', 'status'])]
 * #[Index('guid', ['guid'], unique: true)]
 * ```
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Index
{
    /**
     * @param list<string> $fields columns covered by the index
     */
    public function __construct(
        public string $name,
        public array $fields,
        public bool $unique = false,
    ) {}
}
