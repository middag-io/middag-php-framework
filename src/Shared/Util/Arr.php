<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Util;

/**
 * Small array helpers not covered by the SPL/standard library.
 *
 * @api
 */
final class Arr
{
    /**
     * Merge two arrays, keeping the default when the incoming value is null.
     *
     * For every key in `$overrides`: the value wins unless it is null and the
     * key already exists in `$default` (a null override never clobbers an
     * existing default). Missing keys are added even when their value is null,
     * and non-null values (including `false`, `0`, `''`) always override.
     *
     * Useful for merging partial option/config arrays where "not provided" is
     * expressed as null and must not blank out a default.
     *
     * @param array<array-key, mixed> $default   base array with default values
     * @param array<array-key, mixed> $overrides values to merge in
     *
     * @return array<array-key, mixed> the merged array
     */
    public static function mergePreferNonNull(array $default, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (!array_key_exists($key, $default) || $value !== null) {
                $default[$key] = $value;
            }
        }

        return $default;
    }
}
