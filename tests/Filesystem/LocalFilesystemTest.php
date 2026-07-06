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

    public function testRootWithRedundantInternalSeparatorIsNormalized(): void
    {
        // A redundant internal separator in the root — e.g. when the caller's
        // base dir already ends in "/", as some sys_get_temp_dir() values do —
        // must be normalized so lexical path confinement does not reject every
        // path as escaping the root.
        $child = $this->root . '/child';
        mkdir($child, 0o775, true);

        $fs = new LocalFilesystem($this->root . '//child');
        $fs->write('note.txt', 'hi');

        self::assertSame('hi', $fs->read('note.txt'));
        self::assertTrue($fs->exists('note.txt'));
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

    public function testNullByteInPathIsRejected(): void
    {
        $this->expectException(MiddagInfrastructureException::class);
        $this->fs->read("bad\0.txt");
    }

    public function testDotSegmentsAreNormalisedAway(): void
    {
        $this->fs->write('dir/./note.txt', 'x');

        // The `.` segment collapses, so both paths resolve to the same file.
        self::assertSame('x', $this->fs->read('dir/note.txt'));
    }

    public function testWriteFailsWhenParentPathIsAFile(): void
    {
        $this->fs->write('blocker', 'x');

        // `blocker` is a file, so the parent directory for `blocker/child.txt`
        // cannot be created.
        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('could not be created');
        $this->fs->write('blocker/child.txt', 'y');
    }

    public function testWriteFailsWhenTargetIsADirectory(): void
    {
        mkdir($this->root . '/adir', 0o775, true);

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('could not be written');
        $this->fs->write('adir', 'x');
    }

    public function testReadFailsWhenFileIsUnreadable(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('root bypasses file permissions');
        }

        $this->fs->write('secret.txt', 'x');
        chmod($this->root . '/secret.txt', 0o000);

        try {
            $this->fs->read('secret.txt');
            self::fail('Expected MiddagInfrastructureException');
        } catch (MiddagInfrastructureException $middagInfrastructureException) {
            self::assertStringContainsString('could not be read', $middagInfrastructureException->getMessage());
        } finally {
            chmod($this->root . '/secret.txt', 0o644);
        }
    }

    public function testDeleteFailsWhenUnlinkIsDenied(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('root bypasses file permissions');
        }

        mkdir($this->root . '/ro', 0o775, true);
        file_put_contents($this->root . '/ro/f.txt', 'x');
        // Read+execute but not write on the parent → unlink is denied.
        chmod($this->root . '/ro', 0o555);

        try {
            $this->fs->delete('ro/f.txt');
            self::fail('Expected MiddagInfrastructureException');
        } catch (MiddagInfrastructureException $middagInfrastructureException) {
            self::assertStringContainsString('could not be deleted', $middagInfrastructureException->getMessage());
        } finally {
            chmod($this->root . '/ro', 0o775);
        }
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
