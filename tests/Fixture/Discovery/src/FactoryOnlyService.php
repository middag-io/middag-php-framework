<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Fixture\Discovery\src;

/**
 * Discovery fixture: a `Service` suffix but with a non-public constructor
 * (static-factory pattern), so shouldRegister() rejects it.
 *
 * @internal
 */
final class FactoryOnlyService
{
    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }
}
