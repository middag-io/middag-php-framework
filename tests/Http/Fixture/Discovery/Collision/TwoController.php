<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture\Discovery\Collision;

use Symfony\Component\Routing\Attribute\Route;

/**
 * Collision fixture B: shares the explicit route name `dup` with
 * {@see OneController}. Sorts after it by FQCN, so it is registered last and
 * (deterministically) wins the name.
 *
 * @internal
 */
final class TwoController
{
    #[Route('/dup-two', name: 'dup')]
    public function index(): void {}
}
