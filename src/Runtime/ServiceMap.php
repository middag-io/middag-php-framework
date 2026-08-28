<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Runtime;

use Closure;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * The smallest PSR-11 a standalone process can boot with: a map of ids to
 * instances, or to closures that build them on first use.
 *
 * {@see StandaloneKernel} needs a container to resolve command handlers and
 * signal consumers by class name, and a host that has no framework of its own
 * has nothing to hand it. Requiring such a host to bring a DI container to run
 * one worker would defeat the point of a *minimal* kernel — so this exists, and
 * it is deliberately not more than this. Anything that wants autowiring,
 * tagging or compilation should pass Symfony's container (or any PSR-11)
 * instead; the kernel only ever asks for the interface.
 *
 * Lazy entries are resolved once and memoised, so a handler that opens a
 * connection does it on the first message rather than at boot.
 *
 * @api
 */
final class ServiceMap implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    /**
     * @param array<string, mixed> $services id => instance, or a Closure(ContainerInterface): mixed
     *                                       building it on first get()
     */
    public function __construct(private readonly array $services = []) {}

    /**
     * Add or replace one entry, returning a new map — the kernel wires itself
     * in this way without ever mutating what the caller passed.
     *
     * @param mixed $service an instance, or a Closure(ContainerInterface): mixed
     */
    public function with(string $id, mixed $service): self
    {
        return new self([...$this->services, $id => $service]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (!array_key_exists($id, $this->services)) {
            throw new ServiceNotFoundException($id);
        }

        $service = $this->services[$id];

        return $this->resolved[$id] = $service instanceof Closure ? $service($this) : $service;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
