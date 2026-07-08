<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Request;

use Middag\Framework\Exception\MiddagDomainException;
use Middag\Framework\Http\Request\RequestPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The single source of truth for request input: merges query + body (+ JSON),
 * decodes JSON strictly, and normalises blank strings to null.
 *
 * @internal
 */
#[CoversClass(RequestPayload::class)]
final class RequestPayloadTest extends TestCase
{
    #[Test]
    public function mergesQueryStringWithFormBodyForNonJsonRequests(): void
    {
        $request = Request::create('/form?a=1', 'POST', ['b' => '2']);

        $this->assertSame(['a' => '1', 'b' => '2'], RequestPayload::extract($request));
    }

    #[Test]
    public function mergesQueryStringWithDecodedJsonBody(): void
    {
        $request = $this->jsonRequest('/api?page=2', (string) json_encode(['name' => 'Ada']));

        $this->assertSame(['page' => '2', 'name' => 'Ada'], RequestPayload::extract($request));
    }

    #[Test]
    public function jsonBodyOverridesCollidingQueryKeys(): void
    {
        $request = $this->jsonRequest('/api?name=query', (string) json_encode(['name' => 'body']));

        $this->assertSame(['name' => 'body'], RequestPayload::extract($request));
    }

    #[Test]
    public function whitespaceOnlyJsonBodyFallsBackToTheQueryString(): void
    {
        $request = $this->jsonRequest('/api?page=2', '   ');

        $this->assertSame(['page' => '2'], RequestPayload::extract($request));
    }

    #[Test]
    public function nonArrayJsonBodyIsIgnoredInFavourOfTheQueryString(): void
    {
        // A bare JSON scalar decodes fine but is not a key/value map.
        $request = $this->jsonRequest('/api?q=1', '"just-a-string"');

        $this->assertSame(['q' => '1'], RequestPayload::extract($request));
    }

    #[Test]
    public function malformedJsonBodyRaisesADomainException(): void
    {
        $request = $this->jsonRequest('/api', '{ not valid json');

        $this->expectException(MiddagDomainException::class);

        RequestPayload::extract($request);
    }

    #[Test]
    public function normalisesBlankStringsToNullRecursively(): void
    {
        $request = Request::create('/form', 'POST', [
            'name' => '',
            'kept' => 'value',
            'nested' => ['x' => '', 'y' => 'keep'],
        ]);

        $result = RequestPayload::extract($request);

        $this->assertNull($result['name']);
        $this->assertSame('value', $result['kept']);
        $this->assertNull($result['nested']['x']);
        $this->assertSame('keep', $result['nested']['y']);
    }

    private function jsonRequest(string $uri, string $body): Request
    {
        return Request::create($uri, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }
}
