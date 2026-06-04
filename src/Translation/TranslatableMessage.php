<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Translation;

use JsonSerializable;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A deferred-translation message: a translation `key`, its i18n namespace
 * (`domain`), interpolation `params`, and a resolved English `defaultMessage`
 * fallback.
 *
 * The framework-owned twin of {@see \Middag\Ui\Shared\Data\Translatable} and of
 * Symfony's `TranslatableMessage`, plus a Spring-style `defaultMessage` so the
 * wire always carries a human string even when no catalogue resolves the key.
 * Validation errors carry it; {@see \Middag\Framework\Http\ValidationErrorSerializer}
 * resolves it at the HTTP boundary.
 *
 * @api
 */
final readonly class TranslatableMessage implements JsonSerializable, TranslatableInterface
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $key,
        public string $domain = 'messages',
        public array $params = [],
        public ?string $defaultMessage = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public static function of(string $key, string $domain = 'messages', array $params = [], ?string $defaultMessage = null): self
    {
        return new self($key, $domain, $params, $defaultMessage);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->key, $this->params, $this->domain, $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'key' => $this->key,
            'domain' => $this->domain,
        ];

        if ($this->params !== []) {
            $payload['params'] = $this->params;
        }

        if ($this->defaultMessage !== null) {
            $payload['defaultMessage'] = $this->defaultMessage;
        }

        return $payload;
    }
}
