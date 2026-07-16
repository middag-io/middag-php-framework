<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Util;

use Middag\Framework\Shared\Util\Inflector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Inflector::class)]
final class InflectorTest extends TestCase
{
    #[Test]
    public function pluralizeAppendsS(): void
    {
        self::assertSame('Courses', Inflector::pluralize('Course'));
        self::assertSame('items', Inflector::pluralize('item'));
    }

    #[Test]
    public function slugIsLowercaseAndPluralized(): void
    {
        self::assertSame('courses', Inflector::slug('Course'));
        self::assertSame('modules', Inflector::slug('MODULE'));
    }

    #[Test]
    public function titleIsUcfirstOfSlug(): void
    {
        self::assertSame('Courses', Inflector::title('Course'));
        self::assertSame('Modules', Inflector::title('module'));
    }

    #[Test]
    public function singularIsUcfirstLowercase(): void
    {
        self::assertSame('Course', Inflector::singular('COURSE'));
        self::assertSame('Module', Inflector::singular('module'));
    }

    #[Test]
    public function routePrefixMatchesSlug(): void
    {
        self::assertSame('courses', Inflector::routePrefix('Course'));
        self::assertSame(Inflector::slug('Enrolment'), Inflector::routePrefix('Enrolment'));
    }
}
