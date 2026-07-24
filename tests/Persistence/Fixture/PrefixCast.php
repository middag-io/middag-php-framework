<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Fixture;

use Middag\Framework\Persistence\Contract\CastInterface;

/**
 * Custom cast with a required constructor dependency: it can only be built by a
 * container. Exercises the container instantiation path (the `new` fallback
 * would fatal on the missing argument).
 *
 * @internal
 */
final readonly class PrefixCast implements CastInterface
{
    public function __construct(private string $prefix) {}

    public function get(mixed $value): string
    {
        return $this->prefix . $value;
    }

    public function set(mixed $value): string
    {
        $prefixLength = strlen($this->prefix);

        return str_starts_with((string) $value, $this->prefix)
            ? substr((string) $value, $prefixLength)
            : (string) $value;
    }
}
