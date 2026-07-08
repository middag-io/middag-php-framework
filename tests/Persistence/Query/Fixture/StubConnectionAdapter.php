<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Query\Fixture;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Database\Enum\Capability;
use Middag\Framework\Database\StandardSqlDialect;

/**
 * Minimal in-memory connection stub letting a test dictate the exact row
 * returned by fetch() (used to drive the QueryBuilder aggregate coercion
 * branches deterministically) and whether STREAMING is supported.
 *
 * @internal
 */
final readonly class StubConnectionAdapter implements ConnectionAdapterInterface
{
    /**
     * @param null|array<string, mixed> $fetchResult
     */
    public function __construct(
        private ?array $fetchResult = null,
        private bool $supportsStreaming = true,
    ) {}

    public function execute(string $sql, array $params = []): int
    {
        return 0;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        return $this->fetchResult;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function transaction(callable $work): mixed
    {
        return $work();
    }

    public function insert(string $table, array $record): int
    {
        return 1;
    }

    public function update(string $table, array $record): void {}

    public function delete(string $table, array $conditions): void {}

    public function find(string $table, array $conditions): ?array
    {
        return $this->fetchResult;
    }

    public function findAll(string $table, array $conditions = []): array
    {
        return [];
    }

    public function cursor(string $sql, array $params = []): iterable
    {
        return [];
    }

    public function supports(Capability $feature): bool
    {
        if ($feature === Capability::STREAMING) {
            return $this->supportsStreaming;
        }

        return true;
    }

    public function dialect(): SqlDialectInterface
    {
        return new StandardSqlDialect();
    }
}
