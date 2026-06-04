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

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public contract for controllers executed by the MIDDAG Kernel.
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
 * @api
 */
interface ControllerInterface
{
    public function handle(): void;

    public function setContainer(ContainerInterface $container): void;

    public function setRequest(Request $request): void;

    public function preHandle(): void;

    public function setRequireLogin(): void;

    /**
     * @param array<int, string> $capabilities
     * @param string             $context      Platform-specific context type the adapter interprets
     */
    public function setRequireCapabilities(array $capabilities, string $context = 'system', int $instanceId = 0): void;
}
