<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture\Discovery;

use Symfony\Component\Routing\Attribute\Route;

/**
 * Discovery fixture: a class-level #[Route] contributes a path + name prefix.
 *
 * @internal
 */
#[Route('/beta', name: 'beta.')]
final class BetaController
{
    #[Route('/list', name: 'list')]
    public function list(): void {}
}
