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

use Middag\Framework\Translation\Contract\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * Default OSS fallback translator for standalone single-locale apps.
 *
 * It has no catalogue, so it returns the key itself — but formats it following
 * Symfony's translation pattern via {@see TranslatorTrait}: pipe pluralisation
 * selected by the `%count%` parameter and literal placeholder interpolation
 * (`%name%`, `{{ value }}`, …). Apps that need real translations wire a host
 * adapter (MoodleTranslator wraps `get_string`, WpTranslator wraps `__`/`_n`) —
 * each free to format the host's own way.
 *
 * @api
 */
final class FallbackTranslator implements TranslatorInterface
{
    use TranslatorTrait {
        trans as private;
        getLocale as private;
    }

    public function get(string $key, string $component = '', array $params = []): string
    {
        return $this->trans($key, $params);
    }

    /**
     * Always false: this fallback has no catalogue, so it cannot confirm that a
     * real translation exists for any key (though {@see self::get()} still echoes
     * the key back). A caller branching on "translation present" therefore
     * correctly falls through to its own default.
     */
    public function has(string $key, string $component = ''): bool
    {
        return false;
    }
}
