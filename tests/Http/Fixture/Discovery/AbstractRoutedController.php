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
 * Discovery fixture: an abstract class that DOES carry a #[Route] — must still
 * be skipped by discovery (abstract classes are not instantiable controllers).
 *
 * @internal
 */
abstract class AbstractRoutedController
{
    #[Route('/abstract', name: 'abstract.index')]
    public function index(): void {}
}
