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

use LogicException;
use stdClass;

/**
 * Central utility for type normalization and casting.
 *
 * Prevents values from core or database (usually strings) from reaching higher layers
 * without proper typing (int/bool/float), ensuring adherence to service contracts.
 *
 * @internal
 */
class Typing
{
    /*
     * =========================================================================
     * Integer Normalization
     * =========================================================================
     */

    /**
     * Converts any scalar value to a nullable integer.
     *
     * Expected behavior:
     * - null      → null (not 0)
     * - ""        → null
     * - true      → 1
     * - false     → 0
     * - "10"      → 10
     * - "10abc"   → exception
     *
     * @param mixed $value value to convert
     *
     * @return null|int normalized integer
     */
    public static function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new LogicException(
            'Typing::toInt(): Non-numeric value received: ' . var_export($value, true)
        );
    }

    /**
     * Converts a value to a positive integer or null if zero or negative.
     *
     * @param mixed $value value to convert
     *
     * @return null|int positive integer or null
     */
    public static function toPositiveInt(mixed $value): ?int
    {
        $int = self::toInt($value);

        return ($int !== null && $int > 0) ? $int : null;
    }

    /*
     * =========================================================================
     * Boolean Normalization
     * =========================================================================
     */

    /**
     * Performs strict boolean normalization from various scalar representations.
     *
     * Accepted true values:
     * - true, 1, "1", "true", "yes", "on"
     *
     * Accepted false values:
     * - false, 0, "0", "false", "no", "off", ""
     *
     * Anything else throws exception.
     *
     * @param mixed $value value to convert
     *
     * @return bool normalized boolean
     */
    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));

            $truevals = ['1', 'true', 'yes', 'on'];
            $falsevals = ['0', 'false', 'no', 'off', ''];

            if (in_array($v, $truevals, true)) {
                return true;
            }

            if (in_array($v, $falsevals, true)) {
                return false;
            }
        }

        throw new LogicException(
            'Typing::toBool(): Invalid boolean cast: ' . var_export($value, true)
        );
    }

    /*
     * =========================================================================
     * String Normalization
     * =========================================================================
     */

    /**
     * Performs strict string normalization.
     *
     * Behavior:
     * - null => ""
     * - scalar => (string) cast
     * - Stringable object => __toString
     * - other => throws \LogicException
     *
     * @param mixed $value value to convert
     *
     * @return string normalized string
     */
    public static function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        throw new LogicException(
            'Typing::toString(): Non-stringable value received: ' . var_export($value, true)
        );
    }

    /*
     * =========================================================================
     * Float Normalization
     * =========================================================================
     */

    /**
     * Performs strict float normalization.
     *
     * @param mixed $value value to convert
     *
     * @return null|float normalized float
     */
    public static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new LogicException(
                'Typing::toFloat(): Non-numeric value received: ' . var_export($value, true)
            );
        }

        return (float) $value;
    }

    /*
     * =========================================================================
     * ID Normalization
     * =========================================================================
     */

    /**
     * Converts to nullable integer ID.
     * Returns null for any non-positive value.
     *
     * @param mixed $value ID to normalize
     *
     * @return null|int positive ID or null if invalid/non-positive
     */
    public static function normalizeId(mixed $value): ?int
    {
        $int = self::toInt($value);

        return ($int !== null && $int > 0) ? $int : null;
    }

    /**
     * Converts to integer ID or false.
     * Useful for compatibility with host APIs that use false instead of null.
     *
     * @param mixed $value ID to normalize
     *
     * @return int positive ID or 0
     */
    public static function normalizeIdOrZero(mixed $value): int
    {
        $id = self::normalizeId($value);

        return $id ?? 0;
    }

    /*
     * =========================================================================
     * Record Normalization
     * =========================================================================
     */

    /**
     * Normalizes fields within a host record (stdClass or array) based on a specification.
     *
     * @param array|stdClass $record record object or associative array
     * @param array          $spec   specification, e.g., ['id' => 'int', 'enabled' => 'bool'].
     *
     * @return stdClass normalized record as stdClass
     */
    public static function normalizeRecord(array|stdClass $record, array $spec): stdClass
    {
        $obj = is_array($record) ? (object) $record : clone $record;
        foreach ($spec as $field => $type) {
            if (!property_exists($obj, $field)) {
                continue;
            }
            $val = $obj->{$field};

            $obj->{$field} = match ($type) {
                'int', 'nint' => self::toInt($val),
                'posint' => self::toPositiveInt($val),
                'bool' => self::toBool($val),
                'float' => self::toFloat($val),
                default => (string) $val,
            };
        }

        return $obj;
    }

    /*
     * =========================================================================
     * Semantic Aliases (for improved code readability)
     * =========================================================================
     */

    /**
     * Semantic alias for toInt() used during record normalization.
     *
     * @param mixed $value value to normalize
     *
     * @return null|int normalized integer
     */
    public static function normalize(mixed $value): ?int
    {
        return self::toInt($value);
    }

    /*
     * =========================================================================
     * Array Normalization
     * =========================================================================
     */

    /**
     * Casts all elements of an array to integers (or null).
     *
     * @param array $array input array
     *
     * @return array array with casted integers
     */
    public static function castArrayOfInts(array $array): array
    {
        return array_map(static fn ($v): ?int => self::toInt($v), $array);
    }

    /**
     * Casts all elements of an array to strings.
     *
     * @param array $array input array
     *
     * @return string[] array with string values
     */
    public static function castArrayOfStrings(array $array): array
    {
        return array_map(static fn ($v): string => (string) $v, $array);
    }

    /*
     * =========================================================================
     * Helpers
     * =========================================================================
     */

    /**
     * Checks whether a string contains only digits.
     *
     * @param mixed $value value to check
     *
     * @return bool True if it's a numeric string
     */
    public static function isNumericString(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d+$/', $value) === 1;
    }
}
