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
 * Collision fixture A: shares the explicit route name `dup` with
 * {@see TwoController}. Sorts before it by FQCN, so it is registered first and
 * (deterministically) loses the name to the later class.
 *
 * @internal
 */
final class OneController
{
    #[Route('/dup-one', name: 'dup')]
    public function index(): void {}
}
