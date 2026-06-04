<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Dto;

/**
 * Immutable value object summarizing the outcome of a batch sync/import.
 *
 * Carries the count of successfully processed items, the count of failures,
 * and the human-readable error messages collected along the way.
 * {@see self::isFullSuccess()} is true iff no failures were recorded.
 *
 * @api
 */
final readonly class SyncResult
{
    /**
     * @param string[] $errors Human-readable error messages for failed items
     */
    public function __construct(
        public int $successCount,
        public int $failureCount,
        public array $errors = [],
    ) {}

    /**
     * Whether every item was synchronized without errors.
     */
    public function isFullSuccess(): bool
    {
        return $this->failureCount === 0;
    }
}
