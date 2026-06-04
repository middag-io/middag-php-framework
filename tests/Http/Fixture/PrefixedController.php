<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture;

use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller fixture for the RouteLoader class-level prefix/name tests.
 *
 * @internal
 */
#[Route('/admin', name: 'admin.')]
final class PrefixedController
{
    #[Route('/users', name: 'users')]
    public function users(): void {}

    #[Route('/stats')]
    public function stats(): void {}
}
