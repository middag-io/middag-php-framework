<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Security;

use InvalidArgumentException;
use Middag\Framework\Security\Enum\SignatureEncoding;
use Middag\Framework\Security\HmacSignatureVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Deterministic signature validity, encoding, and the freshness window
 * (issue #56). No host dependency, clock pinned via MockClock.
 *
 * @internal
 */
#[CoversClass(HmacSignatureVerifier::class)]
final class HmacSignatureVerifierTest extends TestCase
{
    private const SECRET = 'whsec_test';

    #[Test]
    public function computesTheKnownHexHmacVector(): void
    {
        $verifier = new HmacSignatureVerifier();

        // Stripe-style signed payload: "{timestamp}.{body}".
        $payload = '1700000000.{"id":"evt_1"}';
        $expected = hash_hmac('sha256', $payload, self::SECRET);

        self::assertSame($expected, $verifier->computeSignature(self::SECRET, $payload));
    }

    #[Test]
    public function verifiesAGenuineSignatureAndRejectsTampering(): void
    {
        $verifier = new HmacSignatureVerifier();
        $payload = '1700000000.{"id":"evt_1"}';
        $signature = $verifier->computeSignature(self::SECRET, $payload);

        self::assertTrue($verifier->verify(self::SECRET, $payload, $signature));
        self::assertFalse($verifier->verify(self::SECRET, $payload . 'x', $signature), 'a mutated body fails');
        self::assertFalse($verifier->verify('wrong_secret', $payload, $signature), 'a wrong secret fails');
        self::assertFalse($verifier->verify(self::SECRET, $payload, 'deadbeef'), 'a wrong-length signature fails without error');
    }

    #[Test]
    public function base64EncodingMatchesHubspotStyleSignatures(): void
    {
        $verifier = new HmacSignatureVerifier('sha256', SignatureEncoding::Base64);
        $payload = 'POSThttps://x/webhook{"ok":true}1700000000';

        $expected = base64_encode(hash_hmac('sha256', $payload, self::SECRET, true));

        self::assertSame($expected, $verifier->computeSignature(self::SECRET, $payload));
        self::assertTrue($verifier->verify(self::SECRET, $payload, $expected));
    }

    #[Test]
    public function freshnessWindowAcceptsRecentAndRejectsStaleOrFuture(): void
    {
        $now = 1_700_000_000;
        $verifier = new HmacSignatureVerifier('sha256', SignatureEncoding::Hex, new MockClock('@' . $now));

        self::assertTrue($verifier->isFresh($now, 300), 'exactly now is fresh');
        self::assertTrue($verifier->isFresh($now - 299, 300), 'just inside the window is fresh');
        self::assertTrue($verifier->isFresh($now + 300, 300), 'boundary is inclusive');
        self::assertFalse($verifier->isFresh($now - 301, 300), 'a stale replay is rejected');
        self::assertFalse($verifier->isFresh($now + 900, 300), 'an implausibly future timestamp is rejected');
    }

    #[Test]
    public function rejectsAnUnsupportedAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HmacSignatureVerifier('not-a-real-algo');
    }
}
