<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Zero-dependency PSR-3 fallback that writes to the PHP error log — the lowest
 * common denominator available on every host and CLI/cron path. Bind it to the
 * container when no richer logger is wired yet.
 *
 * This is NOT the canonical logging path. The canonical channel path is
 * {@see LoggerFactory::forChannel()} (Monolog + rotating file handler); host
 * adapters extend that same base by choosing their `(module, channel)`
 * destinations. This class only covers the bootstrap window and hosts where no
 * factory is wired ({@see LoggerFactory} otherwise hands out a `NullLogger`
 * when logging is disabled).
 *
 * Context values are interpolated into `{placeholders}` per PSR-3; leftover
 * context is appended as JSON so no data is silently dropped.
 *
 * @api
 */
final class ErrorLogFallbackLogger extends AbstractLogger
{
    public function __construct(
        private readonly string $channel = 'middag',
    ) {}

    /**
     * @param mixed[] $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $text = (string) $message;
        $leftover = $context;

        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';

            if (!str_contains($text, $placeholder)) {
                continue;
            }

            $text = str_replace($placeholder, $this->stringify($value), $text);
            unset($leftover[$key]);
        }

        if ($leftover !== []) {
            $encoded = json_encode($leftover, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $text .= ' ' . ($encoded === false ? '[context unserializable]' : $encoded);
        }

        error_log(sprintf('[%s.%s] %s', $this->channel, (string) $level, $text));
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || \is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded === false ? get_debug_type($value) : $encoded;
    }
}
