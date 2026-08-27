<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Retry\Fixture;

use Middag\Framework\Bus\Retry\AttemptableInterface;
use Middag\Framework\Bus\Retry\AttemptStoreInterface;
use Throwable;

/**
 * In-memory {@see AttemptStoreInterface} stub used only to exercise the
 * contract shape in tests. NOT a shippable implementation — a real store is
 * a host detail (a Moodle table, a plain SQL job table, …) that belongs
 * outside this OSS package.
 *
 * @internal
 */
final class InMemoryAttemptStore implements AttemptStoreInterface
{
    /** @var list<int> */
    public array $succeeded = [];

    /** @var list<int> */
    public array $dead = [];

    /** @var array<int, array{exception: Throwable, availableAt: int}> */
    public array $failures = [];

    /** @var array<int, array{item: AttemptableInterface, claimed: bool}> */
    private array $rows = [];

    public function seed(int $id, AttemptableInterface $item): void
    {
        $this->rows[$id] = ['item' => $item, 'claimed' => false];
    }

    public function claim(int $id): ?AttemptableInterface
    {
        if (!isset($this->rows[$id]) || $this->rows[$id]['claimed']) {
            return null;
        }

        $this->rows[$id]['claimed'] = true;

        return $this->rows[$id]['item'];
    }

    public function recordSuccess(int $id): void
    {
        $this->succeeded[] = $id;
    }

    public function recordFailure(int $id, Throwable $e, int $availableAt): void
    {
        $this->failures[$id] = ['exception' => $e, 'availableAt' => $availableAt];
    }

    public function markDead(int $id, Throwable $e): void
    {
        $this->dead[] = $id;
    }
}
