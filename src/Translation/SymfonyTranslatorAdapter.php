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

use Locale;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface as SymfonyTranslatorInterface;

/**
 * Bridges the framework {@see TranslatorInterface} onto Symfony's translator
 * contract so the Validator can localise violation messages through the host
 * i18n system (Moodle `get_string`, WordPress `__`, …).
 *
 * A thin contract bridge only: the wrapped framework translator owns the
 * formatting (pluralisation via `%count%` plus placeholder interpolation),
 * following Symfony's translation pattern. The Symfony message id and parameters
 * are forwarded verbatim, the validator domain mapped to the framework
 * component. With the default {@see FallbackTranslator} (no catalogue) this
 * yields the English messages, correctly interpolated — no standalone regression.
 *
 * @internal
 */
final readonly class SymfonyTranslatorAdapter implements SymfonyTranslatorInterface
{
    public function __construct(private TranslatorInterface $translator) {}

    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->get((string) $id, $domain ?? '', $parameters);
    }

    public function getLocale(): string
    {
        return Locale::getDefault();
    }
}
