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

use InvalidArgumentException;
use Middag\Framework\Security\Contract\SignatureVerifierInterface;
use Middag\Framework\Security\Enum\SignatureEncoding;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Default OSS signature verifier: keyed-hash (HMAC) over a caller-assembled
 * payload, with a constant-time comparison and a freshness window.
 *
 * One instance is configured per provider: the hash algorithm (`sha256` covers
 * HubSpot/Stripe/Jira) and the on-wire encoding ({@see SignatureEncoding}). The
 * caller owns building the signed payload string per provider convention and
 * checking the timestamp with {@see isFresh()} before trusting a request.
 *
 * The freshness clock is injected (PSR-20), so tests pin "now" deterministically
 * and there is no reliance on ambient global time.
 *
 * @api
 */
final readonly class HmacSignatureVerifier implements SignatureVerifierInterface
{
    private ClockInterface $clock;

    public function __construct(
        private string $algorithm = 'sha256',
        private SignatureEncoding $encoding = SignatureEncoding::Hex,
        ?ClockInterface $clock = null,
    ) {
        if (!in_array($algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported HMAC algorithm "%s".', $algorithm));
        }

        $this->clock = $clock ?? new NativeClock();
    }

    public function computeSignature(string $secret, string $payload): string
    {
        $binary = $this->encoding === SignatureEncoding::Base64;
        $digest = hash_hmac($this->algorithm, $payload, $secret, $binary);

        return $binary ? base64_encode($digest) : $digest;
    }

    public function verify(string $secret, string $payload, string $expectedSignature): bool
    {
        return hash_equals($this->computeSignature($secret, $payload), $expectedSignature);
    }

    public function isFresh(int $timestamp, int $toleranceSeconds): bool
    {
        $now = $this->clock->now()->getTimestamp();

        return abs($now - $timestamp) <= $toleranceSeconds;
    }
}
