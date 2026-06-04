<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Security;

use Middag\Framework\Security\Contract\PasswordHasherInterface;

/**
 * Default OSS password hasher backed by PHP's native `password_*` functions.
 *
 * Uses PASSWORD_DEFAULT, so the algorithm tracks PHP's current recommendation
 * (bcrypt today, Argon2 when the runtime promotes it) and existing hashes stay
 * verifiable across upgrades. Host adapters may bind their own implementation
 * to reuse the platform's credential store / cost settings instead.
 *
 * @api
 */
final readonly class NativePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verify(string $plain, string $hash): bool
    {
        // An empty/garbage hash can never match; password_verify already returns
        // false, but bail early to keep the contract obvious.
        if ($hash === '') {
            return false;
        }

        return password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
