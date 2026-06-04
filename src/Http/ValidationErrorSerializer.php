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

use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\TranslatableMessage;

/**
 * Resolves a {@see \Middag\Framework\Exception\MiddagValidationException} error
 * map to its wire form: each {@see TranslatableMessage} becomes
 * `{message, key, domain, params}` (RFC 9457 / Symfony-violation shape), with
 * `message` resolved through the bound translator and falling back to the
 * message's English `defaultMessage`. Raw strings pass through unchanged.
 *
 * @internal
 */
final readonly class ValidationErrorSerializer
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param array<string, string|TranslatableMessage|list<string|TranslatableMessage>> $errors
     *
     * @return array<string, string|array<string, mixed>|list<string|array<string, mixed>>>
     */
    public function serialize(array $errors): array
    {
        $out = [];

        foreach ($errors as $field => $value) {
            $out[$field] = is_array($value)
                ? array_map(fn (string|TranslatableMessage $entry): string|array => $this->entry($entry), $value)
                : $this->entry($value);
        }

        return $out;
    }

    /**
     * @return string|array<string, mixed>
     */
    private function entry(string|TranslatableMessage $error): string|array
    {
        if (is_string($error)) {
            return $error;
        }

        $message = $this->translator->has($error->key, $error->domain)
            ? $this->translator->get($error->key, $error->domain, $error->params)
            : ($error->defaultMessage ?? $error->key);

        return [
            'message' => $message,
            'key' => $error->key,
            'domain' => $error->domain,
            'params' => $error->params,
        ];
    }
}
