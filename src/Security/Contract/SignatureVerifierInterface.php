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

/**
 * Verifies an inbound webhook signature and its freshness.
 *
 * The generic primitive behind HubSpot/Stripe/Jira webhook validation: the
 * caller assembles the provider's signed payload (for Stripe that is
 * `"{timestamp}.{body}"`, for HubSpot v3 `method + uri + body + timestamp`),
 * hands it here with the shared secret and the signature the provider sent, and
 * gets a constant-time yes/no plus a replay-guarding freshness check. It never
 * reads a request object, so it carries no host dependency.
 *
 * @api
 */
interface SignatureVerifierInterface
{
    /**
     * Compute the encoded HMAC of $payload under $secret. Exposed so callers
     * can build provider-specific comparisons; prefer {@see verify()} for the
     * actual check (it compares in constant time).
     */
    public function computeSignature(string $secret, string $payload): string;

    /**
     * Constant-time check that $expectedSignature matches the HMAC of $payload
     * under $secret. Uses `hash_equals`, so it does not leak timing about where
     * a mismatch occurred.
     */
    public function verify(string $secret, string $payload, string $expectedSignature): bool;

    /**
     * Is a signed request fresh enough to accept? True when $timestamp (a Unix
     * time in seconds) is within $toleranceSeconds of now in either direction —
     * rejecting both stale replays and implausibly future-dated requests.
     */
    public function isFresh(int $timestamp, int $toleranceSeconds): bool;
}
