<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging\Fixture;

use Middag\Framework\Logging\Attribute\LogChannel;
use Psr\Log\LoggerInterface;

/**
 * Service that declares its log channel via the attribute.
 *
 * @internal
 */
#[LogChannel('reports', 'audit')]
final readonly class ChanneledService
{
    public function __construct(public LoggerInterface $logger) {}
}
