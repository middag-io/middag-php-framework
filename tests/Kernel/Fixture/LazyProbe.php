<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Fixture;

/**
 * Probe service for lazy-loading tests: counts how many times its real
 * constructor has run, so a test can prove a lazy ghost defers construction
 * until first member access. Non-final so the lazy ghost works across the
 * supported PHP range (8.2/8.3 generate a proxy subclass; 8.4+ ghosts in place).
 */
class LazyProbe
{
    public static int $built = 0;

    public string $value = 'pong';

    public function __construct()
    {
        ++self::$built;
    }

    /**
     * Reads an instance property on purpose: PHP lazy ghosts initialise on
     * property access, so this is what proves construction was deferred.
     */
    public function ping(): string
    {
        return $this->value;
    }
}
