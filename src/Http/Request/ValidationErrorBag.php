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

use Middag\Framework\Translation\Resolver\ViolationKeyMap;
use Middag\Framework\Translation\TranslatableMessage;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Builds the field-keyed validation-error map carried by
 * {@see \Middag\Framework\Exception\MiddagValidationException}: each entry is a
 * {@see TranslatableMessage} (or a list when a field has several errors).
 *
 * Shared by {@see AbstractFormRequest} and {@see DtoHydrator} so both produce an
 * identical shape; field names are normalised to the snake_case key the client
 * sent.
 *
 * @internal
 */
final readonly class ValidationErrorBag
{
    public function __construct(
        private ViolationKeyMap $keyMap = new ViolationKeyMap(),
    ) {}

    /**
     * @return array<string, TranslatableMessage|list<TranslatableMessage>>
     */
    public function fromViolations(ConstraintViolationListInterface $violations): array
    {
        /** @var array<string, TranslatableMessage|list<TranslatableMessage>> $errors */
        $errors = [];

        foreach ($violations as $violation) {
            $this->add($errors, $this->fieldName($violation->getPropertyPath()), $this->messageFor($violation));
        }

        return $errors;
    }

    /**
     * Build the i18n message for one violation: short key + cleaned params + the
     * interpolated English as the default fallback.
     */
    public function messageFor(ConstraintViolationInterface $violation): TranslatableMessage
    {
        return new TranslatableMessage(
            $this->keyMap->keyFor($violation),
            'validators',
            $this->cleanParams($violation->getParameters()),
            (string) $violation->getMessage(),
        );
    }

    /**
     * The fallback message for a denormalization (type-coercion) error, which is
     * not a constraint violation and so has no template of its own.
     */
    public function denormalizationMessage(): TranslatableMessage
    {
        return new TranslatableMessage('validation.invalid_type', 'validators', [], 'This value is not valid.');
    }

    /**
     * Append a message to a field's entry: the first stays scalar, a second
     * promotes the entry to a list.
     *
     * @param array<string, TranslatableMessage|list<TranslatableMessage>> $errors
     */
    public function add(array &$errors, string $field, TranslatableMessage $message): void
    {
        if (!isset($errors[$field])) {
            $errors[$field] = $message;

            return;
        }

        $existing = $errors[$field];
        $errors[$field] = is_array($existing) ? [...$existing, $message] : [$existing, $message];
    }

    /**
     * Map a property path (camelCase, possibly bracketed) to the snake_case wire
     * field name the client sent.
     */
    public function fieldName(?string $path): string
    {
        $path = trim((string) $path, '[]');

        if ($path === '') {
            return '_';
        }

        $snake = preg_replace('/[A-Z]/', '_$0', $path) ?? $path;

        return strtolower(ltrim($snake, '_'));
    }

    /**
     * Strip Symfony's `{{ … }}` / `%…%` placeholder delimiters from parameter
     * keys so the wire carries clean names (`{type:'numeric'}`).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function cleanParams(array $params): array
    {
        $clean = [];

        foreach ($params as $name => $value) {
            $clean[trim((string) $name, '{}% ')] = $value;
        }

        return $clean;
    }
}
