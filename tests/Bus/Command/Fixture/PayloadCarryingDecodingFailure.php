<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Command\Fixture;

use Middag\Framework\Bus\Command\CommandWorker;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Throwable;

/**
 * A decode failure that keeps the raw queued payload on a public property,
 * the way symfony/messenger 8.1 keeps it in encodedEnvelope. Declared here
 * so the same test runs on the 7.4 and 8.x dependency sets:
 * {@see CommandWorker} must never hand this instance, or its payload, to the
 * logger.
 *
 * @internal
 */
final class PayloadCarryingDecodingFailure extends MessageDecodingFailedException
{
    /**
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(string $message, public readonly array $rawPayload, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
