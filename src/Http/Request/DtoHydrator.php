<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Request;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\Resolver\ValidatedDtoResolver;
use Middag\Framework\Translation\TranslatableMessage;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Hydrates request input into a typed DTO and validates it via `#[Assert]`.
 *
 * Used by {@see ValidatedDtoResolver}.
 * Hydration maps snake_case input keys to camelCase DTO properties and coerces
 * scalar types (a numeric string into an `int`/`float` property); a value that
 * cannot be coerced becomes a field error rather than a fatal `TypeError`.
 * Validation then reads the Symfony constraints declared as property
 * attributes. Both error sources — denormalization type errors and constraint
 * violations — are merged into one {@see MiddagValidationException} (HTTP 422),
 * keyed by the snake_case field name so the shape matches
 * {@see AbstractFormRequest}'s `rules()`-array errors.
 *
 * Validation errors are emitted as {@see TranslatableMessage} values via
 * {@see ValidationErrorBag}; translation happens at the HTTP boundary
 * ({@see HttpKernel}).
 *
 * @internal
 */
final readonly class DtoHydrator
{
    /**
     * Hydrate + validate the input into an instance of $dtoClass.
     *
     * @template T of object
     *
     * @param class-string<T>      $dtoClass
     * @param array<string, mixed> $input
     *
     * @return T
     *
     * @throws MiddagValidationException when hydration or validation fails
     */
    public function hydrate(string $dtoClass, array $input): object
    {
        $bag = new ValidationErrorBag();

        /** @var array<string, list<TranslatableMessage>|TranslatableMessage> $errors */
        $errors = [];
        $dto = null;

        try {
            /** @var T $dto */
            $dto = $this->serializer()->denormalize($this->coerceScalars($dtoClass, $input), $dtoClass, null, [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (PartialDenormalizationException $partialDenormalizationException) {
            $data = $partialDenormalizationException->getData();

            /** @var null|T $dto */
            $dto = is_object($data) ? $data : null;

            // getErrors() spans the whole supported Symfony range (^7 || ^8); it
            // is deprecated in 8.1 for getNotNormalizableValueErrors(), which does
            // not exist before 8.1 — revisit when the version floor moves to ^9.
            foreach ($partialDenormalizationException->getErrors() as $error) {
                $bag->add($errors, $bag->fieldName($error->getPath()), $bag->denormalizationMessage());
            }
        }

        if (!is_object($dto)) {
            throw new MiddagValidationException(
                'Validation failed',
                $errors === []
                    ? ['_' => new TranslatableMessage('validation.invalid_payload', 'validators', [], 'Invalid request payload.')]
                    : $errors,
            );
        }

        foreach ($this->validator()->validate($dto) as $violation) {
            $bag->add($errors, $bag->fieldName($violation->getPropertyPath()), $bag->messageFor($violation));
        }

        if ($errors !== []) {
            throw new MiddagValidationException('Validation failed', $errors);
        }

        return $dto;
    }

    /**
     * Pre-coerce stringly-typed HTTP input into the DTO's scalar property types.
     *
     * Query/form values arrive as strings; a numeric string bound for an
     * `int`/`float` property, or "1"/"true"/"on" for a `bool`, is cast here so
     * the denormalizer assigns it cleanly. This reproduces — across the whole
     * supported Symfony range — what serializer 8.1's type conversion does only
     * on PHP 8.4+. Values that cannot be cast (e.g. "abc" for an int) are left
     * untouched, so the denormalizer reports them as field errors rather than
     * coercing them wrongly. Property types are read with native reflection to
     * stay independent of the PropertyInfo type-API changes across 7.x/8.x.
     *
     * @param class-string         $dtoClass
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function coerceScalars(string $dtoClass, array $input): array
    {
        $reflectionClass = new ReflectionClass($dtoClass);
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();

        foreach ($reflectionClass->getProperties() as $property) {
            $type = $property->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }
            if (!$type->isBuiltin()) {
                continue;
            }

            $key = $nameConverter->normalize($property->getName());
            $value = $input[$key] ?? null;

            if (!is_string($value)) {
                continue;
            }

            $input[$key] = match ($type->getName()) {
                'int' => preg_match('/^-?\d++$/', $value) === 1 ? (int) $value : $value,
                'float' => is_numeric($value) ? (float) $value : $value,
                'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value,
                default => $value,
            };
        }

        return $input;
    }

    private function serializer(): Serializer
    {
        $normalizer = new ObjectNormalizer(
            nameConverter: new CamelCaseToSnakeCaseNameConverter(),
            propertyTypeExtractor: new ReflectionExtractor(),
        );

        return new Serializer([
            new BackedEnumNormalizer(),
            new ArrayDenormalizer(),
            $normalizer,
        ]);
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
