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

/**
 * Role: a collaborator into which the kernel injects the DI container.
 *
 * Segregated from {@see ControllerInterface} so an adapter can adopt just
 * container wiring without also committing to the request lifecycle
 * ({@see RequestHandlingInterface}) — e.g. a host REST controller whose
 * dispatch model is the host's own, not the kernel's request → handle() cycle.
 *
 * @api
 */
interface ContainerAwareInterface
{
    public function setContainer(ContainerInterface $container): void;
}
