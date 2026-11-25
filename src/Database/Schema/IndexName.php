<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database\Schema;

/**
 * Index naming shared by the schema adapters.
 *
 * Engines cap identifier length: PostgreSQL silently truncates names longer
 * than 63 bytes, MySQL/MariaDB reject names longer than 64 characters. A name
 * that the engine changes no longer matches the name the adapter computes,
 * so a later drop by name finds nothing. {@see self::fit()} keeps every name
 * under the limit deterministically, so create and drop compute the same
 * name; {@see self::dropCandidates()} also lists the names that adapters
 * stored before this rule, so those indexes can still be dropped.
 *
 * @internal
 */
final class IndexName
{
    /** PostgreSQL's identifier limit (NAMEDATALEN - 1), in bytes. */
    public const int POSTGRESQL_MAX_BYTES = 63;

    /** MySQL/MariaDB's identifier limit (64 characters), measured in bytes here. */
    public const int MYSQL_MAX_BYTES = 64;

    /** Hex characters of the hash appended to a shortened name. */
    private const int HASH_LENGTH = 8;

    private function __construct() {}

    /**
     * Return $name unchanged when it fits in $maxBytes; otherwise cut it and
     * append `_` plus a short hash of the full name. The result is
     * deterministic, valid UTF-8 and at most $maxBytes bytes long, and two
     * names that share a long prefix still get different results.
     */
    public static function fit(string $name, int $maxBytes): string
    {
        if (strlen($name) <= $maxBytes) {
            return $name;
        }

        $hash = substr(hash('sha256', $name), 0, self::HASH_LENGTH);
        $prefix = mb_strcut($name, 0, $maxBytes - self::HASH_LENGTH - 1, 'UTF-8');

        return rtrim($prefix, '_') . '_' . $hash;
    }

    /**
     * The table-qualified name an index gets: `<table>_<name>`, fitted to
     * $maxBytes.
     */
    public static function qualified(string $tableName, string $indexName, int $maxBytes): string
    {
        return self::fit($tableName . '_' . $indexName, $maxBytes);
    }

    /**
     * The name DBAL generates for an index declared without one: `IDX_` (or
     * `UNIQ_` for a unique index) plus the CRC32, in hex, of the table name
     * and of each column, upper-cased and cut to $maxBytes. It repeats the
     * rule of DBAL's deprecated Table::addIndex()/addUniqueIndex(), so an
     * unnamed index keeps the name those methods gave it.
     *
     * @param list<string> $columns
     */
    public static function generated(string $tableName, array $columns, bool $unique, int $maxBytes): string
    {
        $hash = implode('', array_map(
            static fn (string $part): string => dechex(crc32($part)),
            [$tableName, ...$columns],
        ));

        return strtoupper(substr(($unique ? 'uniq' : 'idx') . '_' . $hash, 0, $maxBytes));
    }

    /**
     * Names to try, in order, when dropping the index a descriptor calls
     * $indexName on $tableName:
     *
     * 1. the fitted qualified name (what the adapters emit now);
     * 2. the full qualified name (stored by engines without a length limit,
     *    such as SQLite, before names were fitted);
     * 3. the qualified name clipped to 63 bytes (what PostgreSQL stored when
     *    it silently truncated a long name);
     * 4. the short name (indexes added before names were table-qualified).
     *
     * @return list<string>
     */
    public static function dropCandidates(string $tableName, string $indexName, int $maxBytes): array
    {
        $qualified = $tableName . '_' . $indexName;

        return array_values(array_unique([
            self::fit($qualified, $maxBytes),
            $qualified,
            mb_strcut($qualified, 0, self::POSTGRESQL_MAX_BYTES, 'UTF-8'),
            $indexName,
        ]));
    }
}
