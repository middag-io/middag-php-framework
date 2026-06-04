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

use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\Framework\Persistence\Mapper\AbstractMapper;
use stdClass;

/**
 * Maps the Note entity to/from persistence rows.
 *
 * @internal
 *
 * @extends AbstractMapper<Note>
 */
final class NoteMapper extends AbstractMapper
{
    public function dbToDomain(stdClass $record, array $metadata): Note
    {
        return new Note(
            isset($record->id) ? (int) $record->id : null,
            (string) $record->title,
        );
    }

    public function domainToDb(EntityInterface $entity): stdClass
    {
        return (object) $entity->toArray();
    }
}
