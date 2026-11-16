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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The public surface listed in API-STABILITY.md is exactly the set of types
 * tagged `@api` in src/: a promotion, a demotion or a new `@api` type that the
 * file does not list fails here.
 *
 * @internal
 */
#[CoversNothing]
final class ApiStabilityListTest extends TestCase
{
    private const string DECLARATION = '~/\*\*((?:(?!\*/).)*?)\*/\s*(?:#\[[^\]]*\]\s*)*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)~s';

    #[Test]
    public function theListedPublicSurfaceIsExactlyTheApiTypesInSrc(): void
    {
        $tagged = $this->apiTypesInSrc();
        $listed = $this->listedTypes();

        self::assertNotEmpty($tagged, 'no @api type found: the scan is broken');
        self::assertSame([], array_values(array_diff($tagged, $listed)), '@api in src/ but missing from API-STABILITY.md');
        self::assertSame([], array_values(array_diff($listed, $tagged)), 'listed in API-STABILITY.md but not @api in src/');
        self::assertSame(count($listed), count(array_unique($listed)), 'a type is listed twice');
    }

    /**
     * @return list<string>
     */
    private function apiTypesInSrc(): array
    {
        $types = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());
            if (1 !== preg_match('/^namespace ([^;]+);/m', $code, $namespace)) {
                continue;
            }

            preg_match_all(self::DECLARATION, $code, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (1 === preg_match('/@api\b/', $match[1])) {
                    $types[] = $namespace[1] . '\\' . $match[2];
                }
            }
        }

        sort($types);

        return array_values(array_unique($types));
    }

    /**
     * @return list<string>
     */
    private function listedTypes(): array
    {
        $document = (string) file_get_contents(dirname(__DIR__, 2) . '/API-STABILITY.md');
        $start = strpos($document, "\n## The public surface\n");
        self::assertNotFalse($start, 'API-STABILITY.md has no "## The public surface" section');

        $end = strpos($document, "\n## ", $start + 1);
        $section = substr($document, $start, false === $end ? null : $end - $start);

        preg_match_all('/^- `(Middag\\\Framework\\\[^`]+)`$/m', $section, $matches);

        $types = $matches[1];
        sort($types);

        return $types;
    }
}
