<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

/**
 * Contract for mappers that convert between persistence records (assoc arrays)
 * and domain entities.
 *
 * Records are plain associative arrays (`array<string, mixed>`) at this seam —
 * array-native per the record contract. Hosts whose driver returns objects
 * (e.g. Moodle `$DB` `stdClass` rows) convert to/from arrays at their adapter
 * boundary, never here.
 *
 * @template T of EntityInterface
 *
 * @api
 */
interface MapperInterface
{
    /**
     * Convert a raw persistence record (and optional metadata) into a domain entity.
     *
     * @param array<string, mixed> $record   The raw row from persistence
     * @param array<string, mixed> $metadata Key-value array of related metadata
     *
     * @return T The hydrated domain entity
     */
    public function dbToDomain(array $record, array $metadata): EntityInterface;

    /**
     * Convert a domain entity into a raw persistence record.
     * Note: this usually returns only the main-table record.
     *
     * @param EntityInterface $entity
     *
     * @return array<string, mixed>
     */
    public function domainToDb(EntityInterface $entity): array;
}
