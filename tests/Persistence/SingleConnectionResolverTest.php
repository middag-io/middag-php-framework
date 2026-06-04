<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence;

use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\SingleConnectionResolver;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SingleConnectionResolver::class)]
final class SingleConnectionResolverTest extends TestCase
{
    public function testReturnsTheSameAdapterForDefaultAndNamed(): void
    {
        $adapter = new PdoConnectionAdapter(new PDO('sqlite::memory:'));
        $resolver = new SingleConnectionResolver($adapter);

        self::assertSame($adapter, $resolver->connection());
        self::assertSame($adapter, $resolver->connection('anything'));
    }
}
