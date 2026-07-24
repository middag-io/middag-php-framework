<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Fixture;

use InvalidArgumentException;
use Middag\Framework\Persistence\Contract\CastInterface;

/**
 * Zero-arg custom cast: integer cents (DB) ↔ {@see Money} value object (PHP).
 * Exercises the `new` instantiation fallback (no container needed).
 *
 * @internal
 */
final class MoneyCast implements CastInterface
{
    public function get(mixed $value): Money
    {
        return new Money((int) $value);
    }

    public function set(mixed $value): int
    {
        if (!$value instanceof Money) {
            throw new InvalidArgumentException('MoneyCast expects a Money instance.');
        }

        return $value->cents;
    }
}
