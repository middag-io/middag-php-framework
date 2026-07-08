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
 * Declares a key (primary or foreign) on a {@see Table}-annotated schema class.
 *
 * Repeatable at class level. `$type` is a raw descriptor token
 * (`primary`, `foreign`, `foreign-unique`) preserved verbatim — the platform
 * adapter decides how to render or reconcile it. `$reftable` and `$reffields`
 * are emitted only for foreign keys.
 *
 * ```php
 * #[Key('primary', ['id'], name: 'primary')]
 * #[Key('foreign', ['itemid'], name: 'itemid', reftable: 'middag_items', reffields: ['id'])]
 * ```
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Key
{
    /**
     * @param list<string>      $fields    columns covered by the key
     * @param null|list<string> $reffields referenced columns (foreign keys only)
     */
    public function __construct(
        public string $type,
        public array $fields,
        public string $name,
        public ?string $reftable = null,
        public ?array $reffields = null,
    ) {}
}
