<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Architecture;

use Middag\Framework\Runtime\StandaloneKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The framework has no `src/Adapter/` and must never grow one (core#164 F6
 * acceptance, decision G-standalone).
 *
 * It is host-agnostic by definition, and that is precisely what makes a
 * host-free {@see StandaloneKernel} belong here while lane naming, the signal
 * outbox and the Moodle/WordPress/Standalone adapters belong in
 * `middag-io/core`. An `Adapter/` directory here would be the first step of
 * that boundary dissolving, and the criterion said so in prose — this is the
 * same sentence, executable.
 *
 * @internal
 */
#[CoversNothing]
final class NoHostAdapterDirectoryTest extends TestCase
{
    #[Test]
    public function thereIsNoAdapterDirectoryInSrc(): void
    {
        $src = dirname(__DIR__, 2) . '/src';

        self::assertDirectoryExists($src);
        self::assertDirectoryDoesNotExist(
            $src . '/Adapter',
            'middag-io/framework is host-agnostic: host adapters live in middag-io/core, never here.',
        );
    }

    /**
     * The other half of the same boundary: no source file may name a host.
     * A `Moodle`/`WordPress` namespace reference would smuggle the coupling in
     * without creating the directory the check above looks for.
     */
    #[Test]
    public function noSourceFileReferencesAHostNamespace(): void
    {
        $src = dirname(__DIR__, 2) . '/src';
        $offenders = [];

        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));

        foreach ($files as $path => $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($path);

            // Namespace references only: the words appear in prose (docblocks
            // explaining WHY the host adapters live elsewhere), and forbidding
            // the explanation would be the wrong lesson.
            if (preg_match('/\\\(Moodle|WordPress)\\\/', $code) === 1) {
                $offenders[] = substr($path, strlen($src) + 1);
            }
        }

        sort($offenders);

        self::assertSame([], $offenders, "Host namespaces referenced from the host-agnostic framework:\n" . implode("\n", $offenders));
    }
}
