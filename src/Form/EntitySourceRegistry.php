<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form;

use Middag\Framework\Form\Contract\EntitySourceInterface;
use RuntimeException;

/**
 * Registry of named entity sources for autocomplete/picker fields.
 *
 * The OSS default holder for {@see EntitySourceInterface}: a consumer wires its
 * own source implementations with {@see self::register()} and resolves them by
 * key from its autocomplete endpoint with {@see self::resolve()}. The sources
 * themselves are application/host-specific (they implement the contract); this
 * holder is generic plumbing and ships in the framework so every consumer —
 * standalone apps, host adapters, and downstream packages alike — shares one
 * registry instead of reinventing it.
 *
 * @api
 */
final class EntitySourceRegistry
{
    /** @var array<string, EntitySourceInterface> */
    private array $sources = [];

    public function register(string $key, EntitySourceInterface $source): void
    {
        $this->sources[$key] = $source;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    public function resolve(string $key, string $search = '', int $limit = 20): array
    {
        if (!isset($this->sources[$key])) {
            throw new RuntimeException('Entity source not registered: ' . $key);
        }

        return $this->sources[$key]->resolve($search, $limit);
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }
}
