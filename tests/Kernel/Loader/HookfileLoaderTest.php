<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Loader;

use Middag\Framework\Kernel\Loader\HookfileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HookfileLoader::class)]
final class HookfileLoaderTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    #[Test]
    public function discoverIsCachedAcrossCalls(): void
    {
        $loader = new class([]) extends HookfileLoader {
            public int $discoverCalls = 0;

            /** @param string[] $paths */
            public function __construct(private readonly array $paths)
            {
                parent::__construct();
            }

            protected function discoverPaths(): array
            {
                ++$this->discoverCalls;

                return $this->paths;
            }
        };

        $loader->discover();
        $loader->discover();
        $loader->discover();

        $this->assertSame(1, $loader->discoverCalls);
    }

    #[Test]
    public function loadIncludesFileAndReturnsTrue(): void
    {
        $path = $this->writeHookfile("<?php\n\$GLOBALS['hookfile_loader_test_loaded'] = true;\n");
        $loader = $this->loader([$path]);

        $this->assertTrue($loader->load($path));
        $this->assertTrue($GLOBALS['hookfile_loader_test_loaded'] ?? false);

        unset($GLOBALS['hookfile_loader_test_loaded']);
    }

    #[Test]
    public function loadIsIdempotentOnAlreadyLoadedPath(): void
    {
        $path = $this->writeHookfile("<?php\n\$GLOBALS['hookfile_loader_idem'] = (\$GLOBALS['hookfile_loader_idem'] ?? 0) + 1;\n");
        $loader = $this->loader([$path]);

        $loader->load($path);
        $loader->load($path);
        $loader->load($path);

        $this->assertSame(1, $GLOBALS['hookfile_loader_idem'] ?? 0);
        unset($GLOBALS['hookfile_loader_idem']);
    }

    #[Test]
    public function loadSuspendsOnThrowAndReturnsFalse(): void
    {
        $path = $this->writeHookfile("<?php\nthrow new \\RuntimeException('boom');\n");
        $loader = $this->loader([$path]);

        $this->assertFalse($loader->load($path));
        $this->assertFalse($loader->load($path));
    }

    /** @param string[] $paths */
    private function loader(array $paths): HookfileLoader
    {
        return new class($paths) extends HookfileLoader {
            /** @param string[] $paths */
            public function __construct(private readonly array $paths)
            {
                parent::__construct();
            }

            protected function discoverPaths(): array
            {
                return $this->paths;
            }
        };
    }

    private function writeHookfile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hookfile_test_') . '.php';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
