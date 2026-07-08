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
 * Declares a column on a {@see Table}-annotated schema class.
 *
 * Repeatable at class level; each declaration maps one-to-one to a descriptor
 * column entry. `$type` is a raw dialect token (`int`, `char`, `text`, ...)
 * preserved verbatim so the existing DDL adapters keep their lowercase match
 * arms. `$length` is omitted from the descriptor when null (text columns);
 * `$notnull` and `$sequence` are always emitted; `$default`, `$comment` and
 * `$decimals` are emitted only when set.
 *
 * ```php
 * #[Column('status', 'char', length: 50, notnull: true, default: 'draft')]
 * ```
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public bool $notnull = false,
        public bool $sequence = false,
        public int|string|null $default = null,
        public ?string $comment = null,
        public ?int $decimals = null,
    ) {}
}
