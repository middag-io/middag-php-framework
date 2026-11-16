<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Tooling;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The parse lint is a CI gate (`composer lint` on the PHP 8.3 job), so it
 * must fail closed: a parse error, a missing path, an empty path and a
 * missing Docker for `lint:php83` all exit non-zero. A broken file is written
 * to a temporary directory at runtime; committing one under tests/ would
 * break the lint itself.
 *
 * @internal
 */
#[CoversNothing]
final class LintScriptTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/middag-lint-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->dir);
    }

    #[Test]
    public function passesWhenEveryFileParses(): void
    {
        file_put_contents($this->dir . '/good.php', "<?php\n\necho 1;\n");

        [$status, $output] = $this->runScript(['sh', 'bin/lint-php.sh', $this->dir]);

        self::assertSame(0, $status, $output);
        self::assertStringContainsString('1 files parse on PHP ' . PHP_VERSION, $output);
    }

    #[Test]
    public function failsOnAParseError(): void
    {
        file_put_contents($this->dir . '/good.php', "<?php\n\necho 1;\n");
        file_put_contents($this->dir . '/broken.php', "<?php\n\necho ;\n");

        [$status, $output] = $this->runScript(['sh', 'bin/lint-php.sh', $this->dir]);

        self::assertNotSame(0, $status);
        self::assertStringContainsString('broken.php', $output);
        self::assertStringContainsString('parse errors found', $output);
    }

    #[Test]
    public function failsWhenThereIsNothingToLint(): void
    {
        [$status, $output] = $this->runScript(['sh', 'bin/lint-php.sh', $this->dir]);

        self::assertNotSame(0, $status);
        self::assertStringContainsString('no PHP file found', $output);
    }

    #[Test]
    public function failsOnAMissingPath(): void
    {
        [$status, $output] = $this->runScript(['sh', 'bin/lint-php.sh', $this->dir . '/missing']);

        self::assertNotSame(0, $status);
        self::assertStringContainsString('path not found', $output);
    }

    #[Test]
    public function thePhp83LintFailsWithoutDocker(): void
    {
        [$status, $output] = $this->runScript(['/bin/sh', 'bin/lint-php83.sh'], ['PATH' => $this->dir]);

        self::assertNotSame(0, $status);
        self::assertStringContainsString('nothing was linted', $output);
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $env     replaces PATH when given; PHP_BINARY points at this PHP
     *
     * @return array{int, string}
     */
    private function runScript(array $command, array $env = []): array
    {
        $environment = array_merge(getenv(), ['PHP_BINARY' => PHP_BINARY], $env);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, self::ROOT, $environment);
        self::assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [proc_close($process), $output];
    }
}
