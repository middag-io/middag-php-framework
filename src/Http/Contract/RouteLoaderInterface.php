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
use Symfony\Component\Routing\RouteCollection;

/**
 * Contract for route loaders that scan and register controller routes.
 *
 * @internal
 */
interface RouteLoaderInterface
{
    /**
     * Scan a specific class for Route attributes and add them to the collection.
     *
     * @param RouteCollection    $collection The collection to populate
     * @param ContainerInterface $container  The container to register controllers
     * @param null|string        $className  The FQCN to scan (null to scan all)
     */
    public function loadRoutes(RouteCollection $collection, ContainerInterface $container, ?string $className): void;
}
