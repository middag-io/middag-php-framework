<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Redactor;

use Middag\Framework\Logging\Contract\SecretRedactorInterface;

/**
 * Default secret redactor: replaces values of well-known sensitive keys with a
 * fixed placeholder, recursing into nested arrays.
 *
 * Key matching is case-insensitive and substring-based, so `user_password`,
 * `API_KEY` and `authorizationHeader` all match. The built-in key list covers
 * the common credential/token/PII surface; pass `$additionalKeys` to extend it
 * for a specific project, or bind a different {@see SecretRedactorInterface} to
 * replace the strategy entirely.
 *
 * @api
 */
final readonly class DefaultSecretRedactor implements SecretRedactorInterface
{
    public const REDACTED = '[REDACTED]';

    /**
     * Built-in sensitive key fragments (lower-case, matched as substrings).
     *
     * @var list<string>
     */
    private const DEFAULT_KEYS = [
        'password',
        'passwd',
        'pwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'authorization',
        'auth',
        'credential',
        'private_key',
        'card_number',
        'cardnumber',
        'cvv',
        'cvc',
        'ssn',
    ];

    /** @var list<string> */
    private array $sensitiveKeys;

    /**
     * @param list<string> $additionalKeys extra key fragments to redact (case-insensitive)
     */
    public function __construct(array $additionalKeys = [])
    {
        $keys = array_merge(self::DEFAULT_KEYS, $additionalKeys);
        $this->sensitiveKeys = array_values(array_unique(array_map('strtolower', $keys)));
    }

    /**
     * {@inheritDoc}
     */
    public function redact(array $context): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $result[$key] = self::REDACTED;

                continue;
            }

            $result[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $result;
    }

    private function isSensitive(string $key): bool
    {
        $haystack = strtolower($key);

        foreach ($this->sensitiveKeys as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
