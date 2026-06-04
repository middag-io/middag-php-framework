<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form;

use Middag\Framework\Form\Contract\EntitySourceInterface;
use Middag\Framework\Form\EntitySourceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(EntitySourceRegistry::class)]
final class EntitySourceRegistryTest extends TestCase
{
    #[Test]
    public function resolvesAregisteredSourceByKey(): void
    {
        $registry = new EntitySourceRegistry();
        $registry->register('customers', $this->source([
            ['value' => 1, 'label' => 'Ada'],
            ['value' => 2, 'label' => 'Alan'],
        ]));

        self::assertSame(
            [['value' => 1, 'label' => 'Ada'], ['value' => 2, 'label' => 'Alan']],
            $registry->resolve('customers'),
        );
    }

    #[Test]
    public function forwardsSearchAndLimitToTheSource(): void
    {
        $source = new class implements EntitySourceInterface {
            public string $search = '';

            public int $limit = 0;

            public function resolve(string $search = '', int $limit = 20): array
            {
                $this->search = $search;
                $this->limit = $limit;

                return [];
            }
        };

        $registry = new EntitySourceRegistry();
        $registry->register('agents', $source);
        $registry->resolve('agents', 'ad', 5);

        self::assertSame('ad', $source->search);
        self::assertSame(5, $source->limit);
    }

    #[Test]
    public function reportsWhetherAkeyIsRegistered(): void
    {
        $registry = new EntitySourceRegistry();
        self::assertFalse($registry->has('customers'));

        $registry->register('customers', $this->source([]));

        self::assertTrue($registry->has('customers'));
    }

    #[Test]
    public function throwsWhenResolvingAnUnregisteredKey(): void
    {
        $registry = new EntitySourceRegistry();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Entity source not registered: missing');

        $registry->resolve('missing');
    }

    /**
     * @param array<int, array{value: mixed, label: string}> $rows
     */
    private function source(array $rows): EntitySourceInterface
    {
        return new class($rows) implements EntitySourceInterface {
            /** @param array<int, array{value: mixed, label: string}> $rows */
            public function __construct(private readonly array $rows) {}

            public function resolve(string $search = '', int $limit = 20): array
            {
                return $this->rows;
            }
        };
    }
}
