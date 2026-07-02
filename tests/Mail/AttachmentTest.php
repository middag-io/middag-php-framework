<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Mail;

use InvalidArgumentException;
use Middag\Framework\Mail\Attachment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Attachment::class)]
final class AttachmentTest extends TestCase
{
    public function testRegularAttachmentDefaults(): void
    {
        $attachment = new Attachment('/tmp/report.pdf');

        self::assertSame('/tmp/report.pdf', $attachment->path);
        self::assertSame('report.pdf', $attachment->filename());
        self::assertNull($attachment->mimeType);
        self::assertNull($attachment->contentId);
        self::assertFalse($attachment->isEmbedded());
    }

    public function testExplicitNameAndMime(): void
    {
        $attachment = new Attachment('/tmp/x.bin', 'invoice.pdf', 'application/pdf');

        self::assertSame('invoice.pdf', $attachment->filename());
        self::assertSame('application/pdf', $attachment->mimeType);
    }

    public function testEmbeddedShorthand(): void
    {
        $attachment = Attachment::embedded('/tmp/logo.png', 'logo', 'image/png');

        self::assertSame('logo', $attachment->contentId);
        self::assertSame('image/png', $attachment->mimeType);
        self::assertTrue($attachment->isEmbedded());
    }

    public function testRejectsEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Attachment('');
    }
}
