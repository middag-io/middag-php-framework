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
 * Declares a database table on a schema class.
 *
 * A {@see Middag\Framework\Database\Schema\SchemaAttributeReader} reflects the
 * class-level `#[Table]` plus its repeatable `#[Column]`, `#[Key]` and
 * `#[Index]` siblings into the platform-agnostic descriptor array that
 * {@see Middag\Framework\Database\Schema\SchemaBuilder} and the DDL adapters
 * already consume — typed, IDE-navigable schema authoring instead of untyped
 * PHP-array descriptor files.
 *
 * ```php
 * #[Table('middag_items', comment: 'Current state for framework items')]
 * #[Column('id', 'int', length: 10, notnull: true, sequence: true)]
 * #[Column('type', 'char', length: 100, notnull: true, default: 'generic')]
 * #[Key('primary', ['id'], name: 'primary')]
 * #[Index('type', ['type'])]
 * final class MiddagItemsSchema {}
 * ```
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(
        public string $name,
        public ?string $comment = null,
    ) {}
}
