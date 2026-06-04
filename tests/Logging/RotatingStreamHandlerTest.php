<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging;

use DateTimeImmutable;
use Middag\Framework\Logging\Handler\RotatingStreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RotatingStreamHandler::class)]
final class RotatingStreamHandlerTest extends TestCase
{
    private const CAP = 5 * 1024 * 1024;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/middag-rsh-' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    #[Test]
    public function writesRecordToHourBucket(): void
    {
        $handler = new RotatingStreamHandler($this->root, 'ext', 'chan');
        $handler->handle($this->record('hello bucket'));

        $files = $this->logFiles();
        self::assertCount(1, $files);
        self::assertStringContainsString('hello bucket', (string) file_get_contents($files[0]));
    }

    #[Test]
    public function escalatesToErrorLogWithoutThrowingWhenDirectoryCannotBeCreated(): void
    {
        // basePath is an existing *file*, so creating base/ext/chan must fail.
        $file = $this->root . '/not-a-dir';
        file_put_contents($file, 'x');
        $handler = new RotatingStreamHandler($file, 'ext', 'chan');

        // A logging handler must NOT throw (callers' defensive catch-and-log sites
        // would crash); the line is escalated to error_log instead of dropped.
        $errlog = $this->root . '/errors.log';
        $prev = ini_set('error_log', $errlog);

        try {
            $handler->handle($this->record('boom'));
        } finally {
            if ($prev !== false) {
                ini_set('error_log', $prev);
            }
        }

        self::assertDirectoryDoesNotExist($file . '/ext/chan');
        self::assertStringContainsString('boom', (string) file_get_contents($errlog));
    }

    #[Test]
    public function spillRespectsCapInsteadOfAppendingToFullFile(): void
    {
        $dir = $this->root . '/ext/chan';
        mkdir($dir, 0777, true);
        // Fill the hour bucket past the cap so the next write spills.
        file_put_contents($dir . '/' . date('Y-m-d-H-00-00') . '.log', str_repeat('x', self::CAP));

        $handler = new RotatingStreamHandler($this->root, 'ext', 'chan');
        $handler->handle($this->record('first spill line'));

        $spills = $this->spillFiles($dir);
        self::assertCount(1, $spills, 'overflow must create exactly one spill file');
        $first = $spills[0];

        // Fill that spill file past the cap, then write again.
        file_put_contents($first, str_repeat('y', self::CAP));
        clearstatcache();
        $sizeBefore = filesize($first);

        $handler->handle($this->record('second spill line'));

        // The full spill file must NOT be appended to; the line rolled elsewhere.
        clearstatcache();
        self::assertSame($sizeBefore, filesize($first));
        self::assertStringNotContainsString('second spill line', (string) file_get_contents($first));
        self::assertGreaterThanOrEqual(2, count($this->spillFiles($dir)));
    }

    private function record(string $message): LogRecord
    {
        return new LogRecord(new DateTimeImmutable(), 'chan', Level::Info, $message);
    }

    /**
     * @return list<string>
     */
    private function logFiles(): array
    {
        $found = glob($this->root . '/ext/chan/*.log');

        return $found === false ? [] : array_values($found);
    }

    /**
     * Spill files are the second-granular ones, i.e. every *.log except the
     * hour bucket.
     *
     * @return list<string>
     */
    private function spillFiles(string $dir): array
    {
        $bucket = $dir . '/' . date('Y-m-d-H-00-00') . '.log';
        $found = glob($dir . '/*.log');

        return $found === false ? [] : array_values(array_filter($found, static fn (string $f): bool => $f !== $bucket));
    }

    private function rrmdir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->rrmdir($full) : unlink($full);
        }

        rmdir($path);
    }
}
