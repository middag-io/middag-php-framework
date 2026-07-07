<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http;

use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * Creates PSR-18 clients backed by Symfony HttpClient.
 *
 * @api
 */
final class HttpClientFactory
{
    /**
     * @param array<string, mixed> $options Symfony HttpClient options
     */
    public function make(?string $baseUri = null, array $options = []): ClientInterface
    {
        if ($baseUri !== null && $baseUri !== '') {
            $options['base_uri'] = $baseUri;
        }

        return new Psr18Client(HttpClient::create($options));
    }
}
