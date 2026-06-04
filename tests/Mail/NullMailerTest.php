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
use Middag\Framework\Mail\Contract\MailerInterface;
use Middag\Framework\Mail\Mail;
use Middag\Framework\Mail\NullMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullMailer::class)]
#[CoversClass(Mail::class)]
final class NullMailerTest extends TestCase
{
    public function testSendDiscardsWithoutError(): void
    {
        $mailer = new NullMailer();

        self::assertInstanceOf(MailerInterface::class, $mailer);
        $mailer->send(new Mail(to: ['user@example.test'], subject: 'Hi', body: 'Hello'));

        // Reaching this line proves the no-op default returned without throwing.
        self::assertTrue(true);
    }

    public function testMailExposesAllFields(): void
    {
        $mail = new Mail(
            to: ['a@example.test', 'b@example.test'],
            subject: 'Subject',
            body: 'Text',
            htmlBody: '<p>Text</p>',
            from: 'noreply@example.test',
            cc: ['c@example.test'],
            bcc: ['d@example.test'],
            attachments: ['/tmp/file.pdf'],
        );

        self::assertSame(['a@example.test', 'b@example.test'], $mail->to);
        self::assertSame('Subject', $mail->subject);
        self::assertSame('Text', $mail->body);
        self::assertSame('<p>Text</p>', $mail->htmlBody);
        self::assertSame('noreply@example.test', $mail->from);
        self::assertSame(['c@example.test'], $mail->cc);
        self::assertSame(['d@example.test'], $mail->bcc);
        self::assertSame(['/tmp/file.pdf'], $mail->attachments);
    }

    public function testMailDefaultsOptionalFields(): void
    {
        $mail = new Mail(to: ['a@example.test'], subject: 'S', body: 'B');

        self::assertNull($mail->htmlBody);
        self::assertNull($mail->from);
        self::assertSame([], $mail->cc);
        self::assertSame([], $mail->bcc);
        self::assertSame([], $mail->attachments);
    }

    public function testMailRejectsEmptyRecipients(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Mail(to: [], subject: 'S', body: 'B');
    }
}
