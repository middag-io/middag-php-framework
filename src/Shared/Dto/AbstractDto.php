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

use Middag\Framework\Shared\Contract\DtoInterface;

/**
 * Abstract base DTO for shared data structures.
 *
 * Provides default JSON serialization delegating to `toArray()` implementations.
 *
 * @api
 *
 * @see DtoInterface
 */
abstract class AbstractDto implements DtoInterface
{
    /**
     * Default implementation to serialize to JSON using the array representation.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
