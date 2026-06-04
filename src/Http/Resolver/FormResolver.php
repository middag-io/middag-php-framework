<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Resolver;

use Middag\Framework\Http\Contract\MethodArgumentResolverInterface;
use Middag\Ui\Form\Contract\FormInterface;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Form Resolver.
 *
 * Resolves FormInterface subclasses automatically, hydrating and validating
 * them before injection into controller methods.
 *
 * On POST/PUT/PATCH/DELETE: hydrates with request payload and triggers validate().
 * On GET (and other safe methods): leaves the form unsubmitted (initial render state).
 *
 * @internal
 *
 * @see MethodArgumentResolverInterface
 * @see FormInterface
 */
final readonly class FormResolver implements MethodArgumentResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
        private Request $request
    ) {}

    /**
     * Determines if the parameter expects a FormInterface implementation.
     */
    public function supports(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_subclass_of($type->getName(), FormInterface::class);
    }

    /**
     * Instantiates or retrieves the form from the container, then hydrates
     * and validates it when the request method is a mutating verb.
     *
     * @param array<string, mixed> $routeParams
     */
    public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
    {
        /** @var ReflectionNamedType $type */
        $type = $parameter->getType();
        $fqcn = $type->getName();

        // Forms require FormValidator via constructor, so they are always resolved
        // through the container (autowired). There is no manual-instantiation fallback
        // unlike FormRequestResolver, because the dependency graph is non-trivial.
        /** @var FormInterface $instance */
        $instance = $this->container->get($fqcn);

        $method = strtoupper($this->request->getMethod());

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $input = $this->request->getPayload()->all();
            $instance->hydrate($input);
            $instance->validate();
        }

        return $instance;
    }
}
