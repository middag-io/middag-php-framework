<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Module\Fixture;

use Middag\Framework\Kernel\Module\AbstractModule;

/**
 * Concrete AbstractModule fixture.
 *
 * Rooted in this Fixture/ directory so AbstractModule::getModuleDirectory()
 * resolves here and the filesystem hook auto-discovery finds RecordingHooks
 * as a sibling.
 *
 * @internal
 */
final class TestableModule extends AbstractModule
{
    protected const MODULE_IDNUMBER = 'testable';
}
