<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http;

use Middag\Framework\Http\Contract\ExceptionRendererInterface;
use Middag\Framework\Http\Contract\HttpKernelInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Builds the framework's PSR-15 HTTP kernel for a standalone composition root.
 *
 * The concrete kernel class is an implementation detail; this factory is the
 * supported way to obtain it. The PSR-7 / HttpFoundation bridges are built
 * inside, so the signature takes only the container, the routes and the
 * options a host chooses.
 *
 * As a container service:
 *
 * ```php
 * $c->register(HttpKernelInterface::class, HttpKernelInterface::class)
 *     ->setFactory([new Reference(HttpKernelFactory::class), 'create'])
 *     ->setArguments([new Reference('service_container'), new Reference(RouteCollection::class)]);
 * ```
 *
 * Host adapters that extend the kernel with platform auth keep their own
 * subclass; this factory serves hosts that use the kernel as it is.
 *
 * @api
 */
final readonly class HttpKernelFactory
{
    /**
     * @param ContainerInterface              $container         resolves controllers and the services the kernel looks up
     *                                                           (authenticator, flash bag, translator, Inertia adapter)
     * @param RouteCollection                 $routes            the routes the kernel matches
     * @param RequestContext                  $context           shared with a URL generator, so generated URLs follow
     *                                                           the request the kernel is handling
     * @param bool                            $debug             render the developer exception page instead of the opaque one
     * @param null|ExceptionRendererInterface $exceptionRenderer replaces the default error rendering
     * @param null|string                     $basePath          entry-point path written into $context as its base URL
     */
    public function create(
        ContainerInterface $container,
        RouteCollection $routes,
        RequestContext $context = new RequestContext(),
        bool $debug = false,
        ?ExceptionRendererInterface $exceptionRenderer = null,
        ?string $basePath = null,
    ): HttpKernelInterface {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $container,
            $routes,
            $context,
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
            $debug,
            $exceptionRenderer,
            $basePath,
        );
    }
}
