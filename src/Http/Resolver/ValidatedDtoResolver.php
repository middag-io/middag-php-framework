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

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Attribute\ValidatedDto;
use Middag\Framework\Http\Contract\MethodArgumentResolverInterface;
use Middag\Framework\Http\Request\DtoHydrator;
use Middag\Framework\Http\Request\RequestPayload;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves controller parameters marked `#[ValidatedDto]` into a validated DTO.
 *
 * `supports()` matches a parameter that carries the
 * {@see ValidatedDto} attribute and is type-hinted with a concrete (non-builtin)
 * class. `resolve()` reads the request input via {@see RequestPayload}, hydrates
 * and validates it through {@see DtoHydrator}, and returns the typed object —
 * throwing {@see MiddagValidationException}
 * (HTTP 422) before the action runs when the payload is invalid.
 *
 * Registered in the resolver chain ahead of the container/route resolvers: it
 * only ever matches an explicitly annotated parameter, so it never competes for
 * unannotated arguments.
 *
 * @internal
 *
 * @see ValidatedDto
 * @see DtoHydrator
 * @see MethodArgumentResolverInterface
 */
final readonly class ValidatedDtoResolver implements MethodArgumentResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
        private Request $request,
    ) {}

    public function supports(ReflectionParameter $parameter): bool
    {
        if ($parameter->getAttributes(ValidatedDto::class) === []) {
            return false;
        }

        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin();
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
    {
        /** @var ReflectionNamedType $type */
        $type = $parameter->getType();

        /** @var class-string $fqcn */
        $fqcn = $type->getName();

        return $this->hydrator()->hydrate($fqcn, RequestPayload::extract($this->request));
    }

    private function hydrator(): DtoHydrator
    {
        $translator = null;

        if ($this->container->has(TranslatorInterface::class)) {
            $candidate = $this->container->get(TranslatorInterface::class);
            $translator = $candidate instanceof TranslatorInterface ? $candidate : null;
        }

        return new DtoHydrator($translator);
    }
}
