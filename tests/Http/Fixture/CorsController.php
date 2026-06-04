<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture;

use Middag\Framework\Http\Attribute\Cors;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller exercising the CORS applier: wildcard, allowlisted, and plain.
 *
 * @internal
 */
final class CorsController
{
    #[Cors(methods: ['GET', 'POST'])]
    public function open(): Response
    {
        return new Response('ok');
    }

    #[Cors(origins: ['https://app.example'], credentials: true)]
    public function restricted(): Response
    {
        return new Response('ok');
    }

    public function plain(): Response
    {
        return new Response('ok');
    }
}
