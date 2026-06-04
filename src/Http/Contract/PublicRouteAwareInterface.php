<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

/**
 * Opt-in capability for controllers that distinguish authenticated from public
 * routes at the framework level.
 *
 * When a route is marked `#[Auth(login: false)]`, the kernel signals a
 * public route by calling {@see self::disableAuthentication()} — but only on
 * controllers that declare this capability. Controllers that do not implement
 * it (e.g. page controllers whose `handle()` already honours the flags) are
 * left untouched, which is why this is a separate opt-in contract rather than a
 * method on the universal ControllerInterface.
 *
 * Platform adapters whose controllers run their own authentication pass
 * (e.g. an API controller doing token + session dual auth) implement this to
 * be told when to skip it.
 *
 * @api
 */
interface PublicRouteAwareInterface
{
    /**
     * Signal that the current route is public — skip the controller's own
     * authentication pass.
     */
    public function disableAuthentication(): void;
}
