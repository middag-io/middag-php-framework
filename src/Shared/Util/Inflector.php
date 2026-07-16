<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Util;

/**
 * Naive word inflection for naming conventions.
 *
 * Turns a single-word entity name into the slug, title, singular and
 * route-prefix forms that convention-based builders (CRUD scaffolding,
 * routing) need. Pluralization is the simple English "+s" rule — enough for
 * the internal naming conventions this backs, not a full linguistic inflector.
 *
 * @api
 */
final class Inflector
{
    /**
     * Pluralize a word with the naive English "+s" rule.
     *
     * `Course` → `Courses`, `item` → `items`. Already-plural or irregular
     * words are not special-cased.
     */
    public static function pluralize(string $word): string
    {
        return $word . 's';
    }

    /**
     * Lowercase, pluralized slug: `Course` → `courses`.
     */
    public static function slug(string $name): string
    {
        return self::pluralize(strtolower($name));
    }

    /**
     * Display title (ucfirst of the slug): `Course` → `Courses`.
     */
    public static function title(string $name): string
    {
        return ucfirst(self::slug($name));
    }

    /**
     * Singular display name (ucfirst lowercase): `COURSE` → `Course`.
     */
    public static function singular(string $name): string
    {
        return ucfirst(strtolower($name));
    }

    /**
     * Route prefix (the lowercase pluralized slug): `Course` → `courses`.
     */
    public static function routePrefix(string $name): string
    {
        return self::slug($name);
    }
}
