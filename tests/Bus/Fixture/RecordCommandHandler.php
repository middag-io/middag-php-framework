<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Fixture;

/**
 * Handler for {@see RecordCommand}; records handled values for assertions.
 *
 * @internal
 */
final class RecordCommandHandler
{
    /** @var list<string> */
    public static array $handled = [];

    public function __invoke(RecordCommand $command): void
    {
        self::$handled[] = $command->value;
    }

    public static function reset(): void
    {
        self::$handled = [];
    }
}
