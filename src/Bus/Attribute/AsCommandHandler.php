<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Attribute;

use Attribute;
use Middag\Framework\Bus\Command\AttributeHandlersLocator;
use Middag\Framework\Bus\Command\ConventionHandlersLocator;

/**
 * Declares the command class a handler service handles.
 *
 * Naming-convention-free counterpart to {@see ConventionHandlersLocator}:
 * hosts whose class naming does not follow the `{CommandFQCN}Handler` convention
 * (e.g. Moodle's snake_case `*_command_handler`) annotate the handler instead,
 * and {@see AttributeHandlersLocator} resolves the
 * binding explicitly. Required because a no-arg `__invoke()` handler gives the
 * bus nothing to infer the handled message type from.
 *
 * @api
 *
 * @example
 *   #[AsCommandHandler(command: process_transaction_command::class)]
 *   final readonly class process_transaction_command_handler {
 *       public function __invoke(process_transaction_command $command): void { ... }
 *   }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCommandHandler
{
    /**
     * @param class-string $command FQCN of the command this handler handles
     */
    public function __construct(
        public string $command,
    ) {}
}
