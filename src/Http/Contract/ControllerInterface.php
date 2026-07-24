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
 * Public contract for a full page controller executed by the MIDDAG Kernel.
 *
 * Lifecycle, in guaranteed order: the kernel injects collaborators
 * (setContainer(), setRequest()), applies the route's auth gate
 * (setRequireLogin()/setRequireCapabilities() derived from #[Auth]), runs
 * preHandle(), then invokes the route-matched action method. handle() is a host
 * page-controller entry point that the kernel does not call directly — an
 * adapter's page controller invokes it from its own flow. Platform-specific
 * capabilities (context levels, sesskeys) are defined in host-specific
 * controller interfaces.
 *
 * This is the composition of three segregated role interfaces
 * ({@see ContainerAwareInterface}, {@see RequestHandlingInterface},
 * {@see AuthorizationAwareInterface}); its total surface is unchanged, so
 * existing implementations remain valid. An adapter whose dispatch model does
 * not match the kernel's request → handle() cycle (e.g. a host REST stack) can
 * implement only the roles it supports instead of this full contract.
 *
 * @api
 */
interface ControllerInterface extends ContainerAwareInterface, RequestHandlingInterface, AuthorizationAwareInterface {}
