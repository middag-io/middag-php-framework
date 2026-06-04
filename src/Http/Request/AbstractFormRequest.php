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

use Middag\Framework\Exception\MiddagDomainException;
use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Contract\FormRequestInterface;
use Middag\Framework\Http\Resolver\FormRequestResolver;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\SymfonyTranslatorAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Base form request validated with the Symfony Validator.
 *
 * Subclasses declare `rules()` returning a map of field => Symfony constraint(s).
 * `FormRequestResolver` instantiates the subclass and calls `validate()` before
 * the controller action runs; failure throws `MiddagValidationException`
 * (HTTP 422) carrying a `field => message(s)` error map.
 *
 * Input is validated as an `Assert\Collection`: each declared field must be
 * present unless wrapped in `Assert\Optional`, and extra input keys are ignored.
 *
 * ```php
 * use Symfony\Component\Validator\Constraints as Assert;
 *
 * public function rules(): array
 * {
 *     return [
 *         'title' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
 *         'email' => new Assert\Email(),
 *         'age'   => new Assert\Optional([new Assert\Type('integer'), new Assert\Positive()]),
 *     ];
 * }
 * ```
 *
 * @api Public override point — subclass to declare per-request validation.
 */
abstract class AbstractFormRequest implements FormRequestInterface
{
    /** @var array<string, mixed> */
    private array $validatedData = [];

    private ?TranslatorInterface $translator = null;

    public function __construct(
        protected readonly Request $request,
    ) {}

    /**
     * Wire the host translator used to localise validation messages.
     *
     * Called by {@see FormRequestResolver} during
     * resolution; standalone apps without a translator keep the default English
     * messages.
     *
     * @internal
     */
    public function setTranslator(?TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * Define validation constraints for this request.
     *
     * @return array<string, array<Constraint>|Constraint> field => constraint(s)
     */
    abstract public function rules(): array;

    /**
     * Run validation. Throws on failure.
     *
     * @throws MiddagValidationException
     */
    public function validate(): void
    {
        $rules = $this->rules();
        $data = $this->input();

        $violations = $this->validator()->validate($data, new Assert\Collection(
            fields: $rules,
            allowExtraFields: true,
            allowMissingFields: false,
        ));

        if (count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $field = trim($violation->getPropertyPath(), '[]');
                $message = (string) $violation->getMessage();

                $errors[$field] = isset($errors[$field])
                    ? [...(array) $errors[$field], $message]
                    : $message;
            }

            throw new MiddagValidationException('Validation failed', $errors);
        }

        $this->validatedData = array_intersect_key($data, $rules);
    }

    /**
     * Get only the validated data (the declared fields present in the input).
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Get all input data from the request (query + body + JSON).
     *
     * A body declared as `application/json` is decoded strictly: malformed JSON
     * raises a {@see MiddagDomainException} (HTTP 400) instead of being silently
     * dropped. An empty body falls back to the query string.
     *
     * @return array<string, mixed>
     *
     * @throws MiddagDomainException when the JSON body is present but malformed
     */
    public function input(): array
    {
        return RequestPayload::extract($this->request);
    }

    private function validator(): ValidatorInterface
    {
        if (!$this->translator instanceof TranslatorInterface) {
            return Validation::createValidator();
        }

        return Validation::createValidatorBuilder()
            ->setTranslator(new SymfonyTranslatorAdapter($this->translator))
            ->setTranslationDomain('validators')
            ->getValidator();
    }
}
