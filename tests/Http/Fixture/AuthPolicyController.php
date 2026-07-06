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
use Middag\Framework\Http\Contract\PublicRouteAwareInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller fixture exercising the kernel's #[Auth] resolution:
 * a class-level #[Auth] with capabilities (inherited by actions that declare
 * none) and a public route that opts into {@see PublicRouteAwareInterface}.
 *
 * @internal
 */
#[Auth(login: true, capabilities: ['mod/foo:edit'])]
final class AuthPolicyController extends AbstractController implements PublicRouteAwareInterface
{
    public bool $authDisabled = false;

    /** No method-level #[Auth]: the class-level attribute (login + caps) applies. */
    public function guarded(): JsonResponse
    {
        return new JsonResponse(['area' => 'guarded']);
    }

    /** Method-level #[Auth(login: false)] overrides the class → public route. */
    #[Auth(login: false)]
    public function open(): JsonResponse
    {
        return new JsonResponse(['area' => 'open']);
    }

    public function disableAuthentication(): void
    {
        $this->authDisabled = true;
    }
}
