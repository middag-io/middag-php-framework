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
 * Attribute-bound handler for {@see RecordCommand} whose name deliberately does
 * NOT follow the `{CommandFQCN}Handler` convention — only the attribute binds it.
 *
 * @internal
 */
#[AsCommandHandler(command: RecordCommand::class)]
final class AttributedRecordHandler
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
