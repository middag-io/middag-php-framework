<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Inertia;

use Closure;
use Middag\Framework\Http\Contract\MergeableInterface;

/**
 * A prop the client merges into its existing value instead of replacing it (Inertia v3 `merge`).
 *
 * The value resolves and is present on every render. On a follow-up partial
 * reload the Inertia client merges it (shallow by default, deep when `$deep`)
 * rather than overwriting — the foundation for infinite-scroll / "load more"
 * feeds, where each page appends to the list already on the client. Pass
 * `matchOn` keys so the client dedupes array items by identity instead of
 * blindly concatenating. Build one through {@see InertiaAdapter::merge()} or
 * {@see InertiaAdapter::deepMerge()}:
 *
 * ```php
 * return InertiaAdapter::render('Feed', [
 *     'posts' => InertiaAdapter::merge($pagePosts, matchOn: ['id']),
 * ]);
 * ```
 *
 * A request may opt a key out of merging for one response via the
 * `X-Inertia-Reset` header (the client then replaces instead of appends); such a
 * key is resolved but not announced as a merge prop.
 *
 * @internal
 */
final readonly class MergeProp implements MergeableInterface
{
    /**
     * @param mixed        $value   The prop value, or a Closure resolving to it
     * @param bool         $deep    Deep- (recursive) vs shallow-merge client-side
     * @param list<string> $matchOn Match keys (relative to this prop) for pagination dedup
     */
    public function __construct(
        private mixed $value,
        private bool $deep = false,
        private array $matchOn = [],
    ) {}

    public function resolve(): mixed
    {
        return $this->value instanceof Closure ? ($this->value)() : $this->value;
    }

    public function deep(): bool
    {
        return $this->deep;
    }

    public function matchOn(): array
    {
        return $this->matchOn;
    }
}
