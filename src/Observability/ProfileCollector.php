<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Observability;

use Middag\Framework\Observability\Contract\ProfileCollectorInterface;

/**
 * In-memory {@see ProfileCollectorInterface} — the batteries-included sink.
 *
 * Holds recorded events for the lifetime of the request; a dev profiler reads
 * {@see self::events()} — the canonical read path defined by the contract — to
 * render the debug bar. {@see self::byCategory()} is a concrete-only convenience
 * that filters that same timeline and is not part of the contract.
 * Bind one in the container and inject it into the bus profiling middleware,
 * the hook manager, and any query logger to get a single timeline.
 *
 * @api
 */
final class ProfileCollector implements ProfileCollectorInterface
{
    /** @var list<array{category: string, label: string, context: array<string, mixed>, duration_ms: null|float}> */
    private array $events = [];

    public function record(string $category, string $label, array $context = [], ?float $durationMs = null): void
    {
        $this->events[] = [
            'category' => $category,
            'label' => $label,
            'context' => $context,
            'duration_ms' => $durationMs,
        ];
    }

    public function events(): array
    {
        return $this->events;
    }

    /**
     * Events filtered to a single category.
     *
     * @return list<array{category: string, label: string, context: array<string, mixed>, duration_ms: null|float}>
     */
    public function byCategory(string $category): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (array $event): bool => $event['category'] === $category,
        ));
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
