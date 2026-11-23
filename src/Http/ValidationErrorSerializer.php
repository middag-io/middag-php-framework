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

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\TranslatableMessage;
use Middag\Ui\Form\FieldError;
use Middag\Ui\Form\FieldMessage;
use Middag\Ui\Form\FormErrors;

/**
 * Resolves a {@see MiddagValidationException} error
 * map to its wire form: each {@see TranslatableMessage} becomes a ui
 * {@see FieldMessage} `{message, key, domain, params?}` (RFC 9457 /
 * Symfony-violation shape), with `message` resolved through the bound
 * translator and falling back to the message's English `defaultMessage`.
 * `params` is omitted when empty. Raw strings pass through unchanged.
 *
 * {@see self::serialize()} gives the array form for the 422 body;
 * {@see self::formErrors()} gives the same messages as a ui `FormErrors` for
 * the session-flashed errors.
 *
 * @internal
 */
final readonly class ValidationErrorSerializer
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param array<string, list<string|TranslatableMessage>|string|TranslatableMessage> $errors
     *
     * @return array<string, array<string, mixed>|list<array<string, mixed>|string>|string>
     */
    public function serialize(array $errors): array
    {
        $out = [];

        foreach ($errors as $field => $value) {
            $out[$field] = is_array($value)
                ? array_map(fn (string|TranslatableMessage $entry): array|string => $this->entry($entry), $value)
                : $this->entry($value);
        }

        return $out;
    }

    /**
     * Resolve the error map to the ui `FormErrors` value object: each field
     * maps to one message or a list of messages ({@see FieldError}), where a
     * {@see TranslatableMessage} becomes a {@see FieldMessage} and a string
     * stays a string. Used for the errors flashed to the session, which the
     * client reads as `useForm().errors`.
     *
     * @param array<string, list<string|TranslatableMessage>|string|TranslatableMessage> $errors
     */
    public function formErrors(array $errors): FormErrors
    {
        $out = [];

        foreach ($errors as $field => $value) {
            $out[$field] = is_array($value)
                ? array_map($this->fieldMessage(...), $value)
                : $this->fieldMessage($value);
        }

        return new FormErrors($out);
    }

    /**
     * @return array<string, mixed>|string
     */
    private function entry(string|TranslatableMessage $error): array|string
    {
        $message = $this->fieldMessage($error);

        return is_string($message) ? $message : $message->jsonSerialize();
    }

    private function fieldMessage(string|TranslatableMessage $error): FieldMessage|string
    {
        if (is_string($error)) {
            return $error;
        }

        return new FieldMessage($this->message($error), $error->key, $error->domain, $error->params);
    }

    private function message(string|TranslatableMessage $error): string
    {
        if (is_string($error)) {
            return $error;
        }

        return $this->translator->has($error->key, $error->domain)
            ? $this->translator->get($error->key, $error->domain, $error->params)
            : ($error->defaultMessage ?? $error->key);
    }
}
