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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;

/**
 * Controller with a cached action and a plain one, for the cache applier test.
 *
 * @internal
 */
final class CachedController
{
    #[Cache(maxage: 3600, public: true, vary: ['Accept-Encoding'])]
    public function fresh(): Response
    {
        return new Response('ok');
    }

    public function plain(): Response
    {
        return new Response('ok');
    }
}
