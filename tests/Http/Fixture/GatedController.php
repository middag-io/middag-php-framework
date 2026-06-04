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

use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller fixture for the HttpKernel #[Auth] gate tests.
 *
 * @internal
 */
final class GatedController extends AbstractController
{
    #[Auth(login: true)]
    public function secret(): JsonResponse
    {
        return new JsonResponse(['area' => 'secret']);
    }

    #[Auth(login: false)]
    public function open(): JsonResponse
    {
        return new JsonResponse(['area' => 'open']);
    }
}
