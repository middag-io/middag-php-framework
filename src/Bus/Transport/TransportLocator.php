<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Transport;

use Middag\Framework\Bus\Contract\TransportInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * A tiny PSR-11 locator mapping a transport alias (e.g. `async`) to its
 * {@see TransportInterface} instance.
 *
 * This is the senders container the Symfony `SendersLocator` resolves against:
 * when a message carries `#[AsMessage('async')]`, the bus looks up the alias
 * here to find the transport to send to. The OSS default binds `async` to
 * {@see InMemoryTransport}; rebind this service to add or replace aliases
 * (e.g. a Doctrine/AMQP transport for durable, cross-process async).
 *
 * @api
 */
final readonly class TransportLocator implements ContainerInterface
{
    /**
     * @param array<string, TransportInterface> $transports keyed by alias
     */
    public function __construct(private array $transports) {}

    public function get(string $id): TransportInterface
    {
        return $this->transports[$id] ?? throw new ServiceNotFoundException($id);
    }

    public function has(string $id): bool
    {
        return isset($this->transports[$id]);
    }
}
