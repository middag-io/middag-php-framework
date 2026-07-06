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

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Minimal controller (not a ControllerInterface) resolved from the container by
 * its FQCN, to exercise the kernel's "Fqcn::method" string-controller path.
 *
 * @internal
 */
final class PlainActionController
{
    public function show(): JsonResponse
    {
        return new JsonResponse(['via' => 'string-controller']);
    }
}
