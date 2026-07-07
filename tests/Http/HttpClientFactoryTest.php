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

use Middag\Framework\Http\HttpClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * @internal
 */
#[CoversClass(HttpClientFactory::class)]
final class HttpClientFactoryTest extends TestCase
{
    #[Test]
    public function createsPsr18ClientBackedBySymfonyHttpClient(): void
    {
        $client = (new HttpClientFactory())->make('https://example.test', [
            'headers' => ['X-Test' => '1'],
        ]);

        self::assertInstanceOf(ClientInterface::class, $client);
        self::assertInstanceOf(Psr18Client::class, $client);
    }
}
