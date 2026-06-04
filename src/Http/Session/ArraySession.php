<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Session;

use Middag\Framework\Http\Contract\SessionInterface;

/**
 * In-memory session store.
 *
 * Holds session state in a plain array — no PHP session, no cookies, no
 * superglobal. Used by tests and CLI workers (and any host that manages its own
 * session transport) where {@see NativeSession} would be inappropriate.
 *
 * @api
 */
final class ArraySession implements SessionInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data = []) {}

    public function start(): void
    {
        // No transport to start — state lives in $this->data.
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function regenerate(bool $deleteOld = true): void
    {
        // No session-id transport: state lives in $this->data, so there is no
        // identifier to rotate. Present only to satisfy the contract.
    }
}
