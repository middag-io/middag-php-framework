<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Query;

use ArrayIterator;
use Middag\Framework\Persistence\Contract\ResultInterface;
use Traversable;

/**
 * Immutable paginated result container.
 *
 * The concrete {@see ResultInterface} returned by the paginate() terminal of
 * QueryBuilder and ModelQuery. Wraps the current page's rows plus the total
 * match count, and exposes both the framework's page()/perpage()/pages()
 * accessors and the Eloquent-style currentPage()/lastPage() aliases.
 *
 * @template T
 *
 * @implements ResultInterface<T>
 *
 * @api
 */
final readonly class Page implements ResultInterface
{
    /**
     * @param array<int, T> $items    rows on the current page (0-indexed list)
     * @param int           $total    total rows matching the query, ignoring pagination
     * @param null|int      $page     1-based page number; null when not paginated
     * @param null|int      $perPage  page size; null when not paginated
     * @param bool          $stdClass whether items are raw stdClass rows rather than domain objects
     */
    public function __construct(
        private array $items,
        private int $total,
        private ?int $page = null,
        private ?int $perPage = null,
        private bool $stdClass = false,
    ) {}

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function isStdClass(): bool
    {
        return $this->stdClass;
    }

    public function isPaginated(): bool
    {
        return $this->page !== null && $this->perPage !== null;
    }

    public function page(): ?int
    {
        return $this->page;
    }

    public function perpage(): ?int
    {
        return $this->perPage;
    }

    public function pages(): int
    {
        if ($this->perPage === null || $this->perPage < 1) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function first(): mixed
    {
        $key = array_key_first($this->items);

        return $key === null ? null : $this->items[$key];
    }

    public function last(): mixed
    {
        $key = array_key_last($this->items);

        return $key === null ? null : $this->items[$key];
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Eloquent-style alias for {@see page()}.
     */
    public function currentPage(): ?int
    {
        return $this->page;
    }

    /**
     * Eloquent-style alias for {@see pages()}.
     */
    public function lastPage(): int
    {
        return $this->pages();
    }
}
