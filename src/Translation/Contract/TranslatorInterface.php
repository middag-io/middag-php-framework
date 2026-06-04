<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Translation\Contract;

use Middag\Framework\Translation\FallbackTranslator;

/**
 * Contract for translating strings.
 *
 * Each host platform implements this using its own i18n system (Moodle
 * `get_string`, WordPress `__`/`_n`, etc.), free to format the host's own way.
 * Implementations follow Symfony's translation pattern: pluralisation is driven
 * by a `%count%` parameter (pipe `singular|plural` or an ICU message) and named
 * parameters are interpolated into the message. The default OSS
 * {@see FallbackTranslator} uses Symfony's
 * reference formatter.
 *
 * @api
 */
interface TranslatorInterface
{
    /**
     * Get a translated string by key, pluralised and interpolated.
     *
     * Pass `%count%` in $params to select a plural form; the remaining entries
     * are substituted into the message (Symfony's translation pattern).
     *
     * @param string               $key       The translation key or message
     * @param string               $component The component/plugin (Symfony domain)
     * @param array<string, mixed> $params    Replacement parameters (incl. `%count%`)
     */
    public function get(string $key, string $component = '', array $params = []): string;

    /**
     * Check if a translation string exists.
     */
    public function has(string $key, string $component = ''): bool;
}
