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

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionEnum;

/**
 * Architecture guard (LB-ENUM-01, D-ENUM-CASING): every enum case in src/
 * MUST be strict PascalCase (PER-CS 2.0) — starts uppercase, alphanumeric
 * only, and contains at least one lowercase letter (rejects ALLCAPS such as
 * `TEXT`/`NA` as well as snake_case and lowercase names). Backed values are
 * free-form; only the case NAME is constrained.
 *
 * @internal
 */
#[CoversNothing]
final class EnumCasePascalCaseTest extends TestCase
{
    private const PASCAL_CASE = '/^[A-Z][A-Za-z0-9]*$/';

    #[Test]
    #[DataProvider('enumProvider')]
    public function enumCasesArePascalCase(string $enumClass): void
    {
        foreach ((new ReflectionEnum($enumClass))->getCases() as $case) {
            $name = $case->getName();

            $this->assertMatchesRegularExpression(
                self::PASCAL_CASE,
                $name,
                sprintf('%s::%s must be PascalCase (no underscores, starts uppercase).', $enumClass, $name)
            );
            $this->assertMatchesRegularExpression(
                '/[a-z]/',
                $name,
                sprintf('%s::%s must contain a lowercase letter (ALLCAPS is not PascalCase).', $enumClass, $name)
            );
        }
    }

    /**
     * Every enum declared under src/. Files are pre-filtered textually so the
     * provider never autoloads host-only classes that fatal outside the host.
     *
     * @return array<string, array{string}>
     */
    public static function enumProvider(): array
    {
        $src = dirname(__DIR__, 2) . '/src';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        );

        $enums = [];

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match('/^\s*enum\s+\w+/m', $contents) !== 1) {
                continue;
            }
            $relative = substr($file->getPathname(), \strlen($src) + 1, -\strlen('.php'));
            $fqcn = 'Middag\Framework\\' . str_replace('/', '\\', $relative);

            if (enum_exists($fqcn)) {
                $enums[$fqcn] = [$fqcn];
            }
        }

        ksort($enums);

        return $enums;
    }
}
