<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Attribute;

use Attribute;

/**
 * Disables automatic output sanitization for a shortcode handler.
 *
 * By default, shortcode output is sanitized by the host's text-cleaning
 * routine before injection into rendered HTML. Handlers annotated with #[TrustedOutput]
 * bypass sanitization — the developer assumes full responsibility for
 * output safety (XSS prevention, encoding, etc.).
 *
 * Usage on a callable class handler:
 *
 *     #[TrustedOutput]
 *     class MyShortcodeHandler
 *     {
 *         public function __invoke(array $attrs): string
 *         {
 *             return '<div class="custom">' . $attrs['content'] . '</div>';
 *         }
 *     }
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
final readonly class TrustedOutput {}
