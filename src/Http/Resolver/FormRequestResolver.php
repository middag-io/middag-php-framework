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

use Middag\Framework\Http\Contract\FormRequestInterface;
use Middag\Framework\Http\Contract\MethodArgumentResolverInterface;
use Middag\Framework\Http\Request\AbstractFormRequest;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Form Request Resolver.
 *
 * Resolves FormRequest classes automatically, validating them before injection.
 * Triggers the `validate()` method immediately upon resolution.
 *
 * @internal
 *
 * @see MethodArgumentResolverInterface
 * @see FormRequestInterface
 */
final readonly class FormRequestResolver implements MethodArgumentResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
        private Request $request
    ) {}

    /**
     * Determines if the parameter expects a FormRequest implementation.
     */
    public function supports(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_subclass_of($type->getName(), FormRequestInterface::class);
    }

    /**
     * Instantiates or retrieves the FormRequest and triggers validation.
     *
     * @param array<string, mixed> $routeParams
     */
    public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
    {
        /** @var ReflectionNamedType $type */
        $type = $parameter->getType();
        $fqcn = $type->getName();

        // 1. Try the container (supports DI in FormRequest constructor).
        //    The container must have the current Request as a synthetic service
        //    if the FormRequest depends on Request.
        if ($this->container->has($fqcn)) {
            $instance = $this->container->get($fqcn);
        } else {
            // 2. Fallback: manual instantiation.
            //    Assumes the FormRequest constructor only accepts the Request object.
            $instance = new $fqcn($this->request);
        }

        // Wire the host translator (when bound) so validation messages are
        // localised through the host i18n system; absent it, English defaults.
        if ($instance instanceof AbstractFormRequest && $this->container->has(TranslatorInterface::class)) {
            $translator = $this->container->get(TranslatorInterface::class);

            if ($translator instanceof TranslatorInterface) {
                $instance->setTranslator($translator);
            }
        }

        $instance->validate();

        return $instance;
    }
}
