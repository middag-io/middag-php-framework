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

use Countable;
use IteratorAggregate;

/**
 * Immutable result container returned by the paginate() terminal of
 * QueryBuilder and ModelQuery.
 *
 * @template T
 *
 * @extends IteratorAggregate<int, T>
 *
 * @api
 */
interface ResultInterface extends Countable, IteratorAggregate
{
    /**
     * @return array<int, T>
     */
    public function items(): array;

    /**
     * Returns total number of items matching the query, ignoring pagination.
     *
     * @return int
     */
    public function total(): int;

    /**
     * Indicates whether the items are stdClass objects instead of Domain objects.
     *
     * @return bool
     */
    public function isStdClass(): bool;

    /**
     * Indicates whether the result is paginated.
     *
     * @return bool
     */
    public function isPaginated(): bool;

    /**
     * Returns current page number.
     */
    public function page(): ?int;

    /**
     * Returns number of items per page.
     */
    public function perpage(): ?int;

    /**
     * Returns total number of pages.
     * When pagination is disabled, always returns 1.
     *
     * @return int
     */
    public function pages(): int;

    /**
     * Returns the first item or null.
     *
     * @return null|T
     */
    public function first(): mixed;

    /**
     * Returns the last item or null.
     *
     * @return null|T
     */
    public function last(): mixed;

    /**
     * Returns true when no items were returned.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Returns true when at least one item exists.
     *
     * @return bool
     */
    public function isNotEmpty(): bool;

    /**
     * Convert to array for JSON or external APIs.
     * Directly returns internal resolved dataset.
     *
     * @return array<int, T>
     */
    public function toArray(): array;
}
