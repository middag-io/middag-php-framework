<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Context;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Principal identifier carried with a command across a persistent transport.
 *
 * Hosts may opt into applying this context while consuming a command. The
 * framework only carries the identifier; it does not impose host policy.
 *
 * @api
 */
final readonly class UserContextStamp implements StampInterface
{
    public function __construct(private ?int $userId) {}

    public function getUserId(): ?int
    {
        return $this->userId;
    }
}
