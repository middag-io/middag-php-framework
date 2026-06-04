<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Security\Contract;

use Middag\Framework\Security\NativePasswordHasher;

/**
 * Infrastructure port for password hashing, so domain/auth code depends on a
 * thin contract instead of calling a host or PHP hashing function directly.
 *
 * Deliberately NOT symfony/password-hasher: the framework declares the seam and
 * ships a PHP-native default; a host adapter may delegate to the platform's own
 * hasher (so credentials stay verifiable against existing host records).
 *
 * Default OSS impl: {@see NativePasswordHasher} (PHP `password_hash`).
 *
 * @api
 */
interface PasswordHasherInterface
{
    /**
     * Hash a plaintext password into a self-describing digest (algorithm, cost
     * and salt embedded), safe to persist.
     */
    public function hash(string $plain): string;

    /**
     * Verify a plaintext password against a previously stored hash, using a
     * constant-time comparison.
     */
    public function verify(string $plain, string $hash): bool;

    /**
     * True when the hash was produced with parameters weaker than the current
     * default and should be re-hashed on the next successful login.
     */
    public function needsRehash(string $hash): bool;
}
