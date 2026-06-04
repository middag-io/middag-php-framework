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

use stdClass;

/**
 * Contract for mappers that convert between persistence records (stdClass)
 * and domain entities.
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
     * @param stdClass             $record   The raw row from persistence
     * @param array<string, mixed> $metadata Key-value array of related metadata
     *
     * @return T The hydrated domain entity
     */
    public function dbToDomain(stdClass $record, array $metadata): EntityInterface;

    /**
     * Convert a domain entity into a raw persistence record.
     * Note: this usually returns only the main-table record.
     *
     * @param EntityInterface $entity
     *
     * @return stdClass
     */
    public function domainToDb(EntityInterface $entity): stdClass;
}
