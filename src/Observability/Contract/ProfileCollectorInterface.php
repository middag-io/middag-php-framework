<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Observability\Contract;

/**
 * Sink for runtime profiling events.
 *
 * Introspection seam: bus dispatches, fired hooks, and (adapter-side) queries
 * record timed events here, so a dev profiler / debug bar can read them back
 * instead of each consumer wiring its own recording decorators. Categories are
 * free-form strings (`bus`, `hook`, `query`, …).
 *
 * @api
 */
interface ProfileCollectorInterface
{
    /**
     * Record a profiling event.
     *
     * @param string               $category   coarse bucket, e.g. `bus`, `hook`, `query`
     * @param string               $label      the specific thing profiled (message class, hook tag, SQL, …)
     * @param array<string, mixed> $context    arbitrary structured metadata
     * @param null|float           $durationMs measured duration in milliseconds, when known
     */
    public function record(string $category, string $label, array $context = [], ?float $durationMs = null): void;

    /**
     * All recorded events in order.
     *
     * @return list<array{category: string, label: string, context: array<string, mixed>, duration_ms: null|float}>
     */
    public function events(): array;

    /**
     * Drop all recorded events.
     */
    public function reset(): void;
}
