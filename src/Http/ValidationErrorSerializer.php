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
use Middag\Ui\Form\FormErrors;

/**
 * Resolves a {@see MiddagValidationException} error
 * map to its wire form: each {@see TranslatableMessage} becomes
 * `{message, key, domain, params}` (RFC 9457 / Symfony-violation shape), with
 * `message` resolved through the bound translator and falling back to the
 * message's English `defaultMessage`. Raw strings pass through unchanged.
 *
 * {@see self::formErrors()} resolves the same map to the ui `FormErrors` shape
 * (message strings only) for the session-flashed errors.
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
     * Resolve the error map to the ui `FormErrors` wire shape: each field maps
     * to one message string or a list of message strings ({@see FieldError}).
     * Used for the errors flashed to the session, which the client reads as
     * `useForm().errors`.
     *
     * @param array<string, list<string|TranslatableMessage>|string|TranslatableMessage> $errors
     */
    public function formErrors(array $errors): FormErrors
    {
        $out = [];

        foreach ($errors as $field => $value) {
            $out[$field] = is_array($value)
                ? array_map($this->message(...), $value)
                : $this->message($value);
        }

        return new FormErrors($out);
    }

    /**
     * @return array<string, mixed>|string
     */
    private function entry(string|TranslatableMessage $error): array|string
    {
        if (is_string($error)) {
            return $error;
        }

        return [
            'message' => $this->message($error),
            'key' => $error->key,
            'domain' => $error->domain,
            'params' => $error->params,
        ];
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
