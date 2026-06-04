<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http;

use InvalidArgumentException;
use Middag\Framework\Http\Attribute\Cors;
use Middag\Framework\Http\Response\CorsHeaderApplier;
use Middag\Framework\Tests\Http\Fixture\CorsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * #[Cors] sets per-route CORS headers on the actual-request response: wildcard
 * passes through, an allowlist echoes the matching origin (with Vary), a
 * disallowed origin gets none.
 *
 * @internal
 */
#[CoversClass(CorsHeaderApplier::class)]
#[CoversClass(Cors::class)]
final class CorsHeaderApplierTest extends TestCase
{
    public function testWildcardOriginPassesThrough(): void
    {
        $response = new Response('ok');

        CorsHeaderApplier::apply(new CorsController(), 'open', $this->request(null), $response);

        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('GET, POST', $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testAllowlistEchoesMatchingOriginAndVaries(): void
    {
        $response = new Response('ok');

        CorsHeaderApplier::apply(new CorsController(), 'restricted', $this->request('https://app.example'), $response);

        self::assertSame('https://app.example', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertContains('Origin', $response->getVary());
    }

    public function testDisallowedOriginGetsNoCorsHeader(): void
    {
        $response = new Response('ok');

        CorsHeaderApplier::apply(new CorsController(), 'restricted', $this->request('https://evil.example'), $response);

        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function testNoOpWhenActionHasNoAttribute(): void
    {
        $response = new Response('ok');

        CorsHeaderApplier::apply(new CorsController(), 'plain', $this->request('https://app.example'), $response);

        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function testCredentialsWithWildcardOriginIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cors(origins: ['*'], credentials: true);
    }

    private function request(?string $origin): Request
    {
        $request = Request::create('/');

        if ($origin !== null) {
            $request->headers->set('Origin', $origin);
        }

        return $request;
    }
}
