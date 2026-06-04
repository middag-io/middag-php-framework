<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Filesystem;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Filesystem\LocalFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LocalFilesystem::class)]
final class LocalFilesystemTest extends TestCase
{
    private string $root;

    private LocalFilesystem $fs;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/middag-fs-' . uniqid('', true);
        mkdir($this->root, 0o775, true);
        $this->fs = new LocalFilesystem($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testWriteThenReadRoundtrip(): void
    {
        self::assertInstanceOf(FilesystemInterface::class, $this->fs);

        $this->fs->write('note.txt', 'hello');

        self::assertSame('hello', $this->fs->read('note.txt'));
    }

    public function testExistsReflectsState(): void
    {
        self::assertFalse($this->fs->exists('missing.txt'));

        $this->fs->write('present.txt', 'x');

        self::assertTrue($this->fs->exists('present.txt'));
    }

    public function testWriteCreatesNestedDirectories(): void
    {
        $this->fs->write('a/b/c.txt', 'deep');

        self::assertSame('deep', $this->fs->read('a/b/c.txt'));
    }

    public function testReadMissingThrows(): void
    {
        $this->expectException(MiddagInfrastructureException::class);

        $this->fs->read('does-not-exist.txt');
    }

    public function testDeleteRemovesFile(): void
    {
        $this->fs->write('temp.txt', 'x');
        $this->fs->delete('temp.txt');

        self::assertFalse($this->fs->exists('temp.txt'));
    }

    public function testDeleteMissingIsNoop(): void
    {
        $this->fs->delete('never-existed.txt');

        self::assertFalse($this->fs->exists('never-existed.txt'));
    }

    public function testTraversalOutsideRootIsBlockedOnWrite(): void
    {
        $this->expectException(MiddagInfrastructureException::class);

        $this->fs->write('../escape.txt', 'should not land outside root');
    }

    public function testTraversalOutsideRootIsBlockedOnRead(): void
    {
        $this->expectException(MiddagInfrastructureException::class);

        $this->fs->read('../../etc/hosts');
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
