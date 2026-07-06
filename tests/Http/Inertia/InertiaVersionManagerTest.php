<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Inertia;

use Middag\Framework\Http\Inertia\InertiaVersionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The Inertia asset-version seam: a manual override wins, otherwise an md5 of
 * the adapter-supplied bundle (when it exists), otherwise the 'dev' fallback.
 *
 * The manager holds process-wide static state (the documented adapter boot
 * seam), so each test resets both statics to their pristine null defaults.
 *
 * @internal
 */
#[CoversClass(InertiaVersionManager::class)]
final class InertiaVersionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetManagerState();
    }

    protected function tearDown(): void
    {
        $this->resetManagerState();
        parent::tearDown();
    }

    #[Test]
    public function getVersionFallsBackToDevWhenNothingIsConfigured(): void
    {
        $this->assertSame('dev', InertiaVersionManager::getVersion());
    }

    #[Test]
    public function setVersionOverridesTheReportedVersion(): void
    {
        InertiaVersionManager::setVersion('1.2.3');

        $this->assertSame('1.2.3', InertiaVersionManager::getVersion());
    }

    #[Test]
    public function getVersionReturnsMd5HashOfAnExistingBundle(): void
    {
        $bundle = (new ReflectionClass(InertiaVersionManager::class))->getFileName();
        $this->assertIsString($bundle);

        InertiaVersionManager::setBundlePath($bundle);

        $this->assertSame(md5_file($bundle), InertiaVersionManager::getVersion());
    }

    #[Test]
    public function getVersionFallsBackToDevWhenTheBundlePathDoesNotExist(): void
    {
        InertiaVersionManager::setBundlePath('/no/such/bundle-' . uniqid() . '.js');

        $this->assertSame('dev', InertiaVersionManager::getVersion());
    }

    #[Test]
    public function manualVersionTakesPrecedenceOverTheBundleHash(): void
    {
        $bundle = (new ReflectionClass(InertiaVersionManager::class))->getFileName();
        $this->assertIsString($bundle);

        InertiaVersionManager::setBundlePath($bundle);
        InertiaVersionManager::setVersion('pinned-9.9.9');

        $this->assertSame('pinned-9.9.9', InertiaVersionManager::getVersion());
        $this->assertNotSame(md5_file($bundle), InertiaVersionManager::getVersion());
    }

    /**
     * Restore the two process-wide statics to their null defaults so sibling
     * tests (and other suites) never inherit a configured version/bundle.
     */
    private function resetManagerState(): void
    {
        $ref = new ReflectionClass(InertiaVersionManager::class);
        $ref->getProperty('manualVersion')->setValue(null, null);
        $ref->getProperty('bundlePath')->setValue(null, null);
    }
}
