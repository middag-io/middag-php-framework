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

use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\Framework\Persistence\Repository\AbstractRepository;

/**
 * Concrete Data-Mapper repository fixture over the 'notes' table.
 *
 * @internal
 *
 * @extends AbstractRepository<Note>
 */
final class NoteRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'notes';
    }

    /**
     * @return MapperInterface<Note>
     */
    protected function mapper(): MapperInterface
    {
        return new NoteMapper();
    }
}
