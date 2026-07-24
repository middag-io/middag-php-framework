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

use Symfony\Component\HttpFoundation\Request;

/**
 * Role: the kernel request lifecycle — the collaborator receives the Request,
 * runs preHandle(), then handle() is invoked as the page-controller entry
 * point.
 *
 * Segregated from {@see ControllerInterface} so this is the piece an adapter
 * opts OUT of when its dispatch model is not the kernel's single
 * request → handle() cycle (e.g. WordPress REST registers N routes, each with
 * its own callback + permission_callback, driven by WP_REST_Request). Such an
 * adapter can still be {@see ContainerAwareInterface} without faking this role.
 *
 * @api
 */
interface RequestHandlingInterface
{
    public function setRequest(Request $request): void;

    public function preHandle(): void;

    public function handle(): void;
}
