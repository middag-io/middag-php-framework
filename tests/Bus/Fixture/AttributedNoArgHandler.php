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

use Middag\Framework\Bus\Attribute\AsCommandHandler;

/**
 * Attribute-bound handler with a no-arg __invoke(): the message type cannot be
 * inferred from the signature, so only the attribute can bind it.
 *
 * @internal
 */
#[AsCommandHandler(command: AsyncRoutedCommand::class)]
final class AttributedNoArgHandler
{
    public static int $count = 0;

    public function __invoke(): void
    {
        ++self::$count;
    }

    public static function reset(): void
    {
        self::$count = 0;
    }
}
