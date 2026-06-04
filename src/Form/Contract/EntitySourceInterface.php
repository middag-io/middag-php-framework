<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form\Contract;

/**
 * Resolves entity selection candidates for autocomplete fields.
 *
 * @api
 */
interface EntitySourceInterface
{
    /**
     * Resolve entity selection candidates by search term.
     *
     * @return array<int, array{value: mixed, label: string}>
     */
    public function resolve(string $search = '', int $limit = 20): array;
}
