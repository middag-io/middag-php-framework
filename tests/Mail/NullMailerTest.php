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
use Middag\Framework\Mail\Address;
use Middag\Framework\Mail\Attachment;
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
        $mailer->send(new Mail(to: [new Address('user@example.test')], subject: 'Hi', body: 'Hello'));

        // Reaching this line proves the no-op default returned without throwing.
        self::assertTrue(true);
    }

    public function testMailExposesAllFields(): void
    {
        $to = [new Address('a@example.test'), new Address('b@example.test', 'B User')];
        $cc = [new Address('c@example.test')];
        $bcc = [new Address('d@example.test')];
        $from = new Address('noreply@example.test', 'No Reply');
        $replyTo = new Address('support@example.test');
        $attachments = [
            new Attachment('/tmp/file.pdf'),
            Attachment::embedded('/tmp/logo.png', 'logo', 'image/png'),
        ];

        $mail = new Mail(
            to: $to,
            subject: 'Subject',
            body: 'Text',
            htmlBody: '<p>Text <img src="cid:logo"></p>',
            from: $from,
            replyTo: $replyTo,
            cc: $cc,
            bcc: $bcc,
            attachments: $attachments,
        );

        self::assertSame($to, $mail->to);
        self::assertSame('Subject', $mail->subject);
        self::assertSame('Text', $mail->body);
        self::assertSame('<p>Text <img src="cid:logo"></p>', $mail->htmlBody);
        self::assertSame($from, $mail->from);
        self::assertSame($replyTo, $mail->replyTo);
        self::assertSame($cc, $mail->cc);
        self::assertSame($bcc, $mail->bcc);
        self::assertSame($attachments, $mail->attachments);
    }

    public function testMailDefaultsOptionalFields(): void
    {
        $mail = new Mail(to: [new Address('a@example.test')], subject: 'S', body: 'B');

        self::assertNull($mail->htmlBody);
        self::assertNull($mail->from);
        self::assertNull($mail->replyTo);
        self::assertSame([], $mail->cc);
        self::assertSame([], $mail->bcc);
        self::assertSame([], $mail->attachments);
    }

    public function testMailNormalisesStringsToValueObjects(): void
    {
        $mail = new Mail(
            to: ['Jane Doe <jane@example.test>', new Address('bob@example.test')],
            subject: 'S',
            body: 'B',
            from: 'No Reply <noreply@example.test>',
            replyTo: 'support@example.test',
            cc: ['c@example.test'],
            attachments: ['/tmp/report.pdf', Attachment::embedded('/tmp/logo.png', 'logo')],
        );

        self::assertSame('jane@example.test', $mail->to[0]->email);
        self::assertSame('Jane Doe', $mail->to[0]->name);
        self::assertSame('bob@example.test', $mail->to[1]->email);
        self::assertSame('noreply@example.test', $mail->from?->email);
        self::assertSame('No Reply', $mail->from?->name);
        self::assertSame('support@example.test', $mail->replyTo?->email);
        self::assertSame('c@example.test', $mail->cc[0]->email);
        self::assertSame('/tmp/report.pdf', $mail->attachments[0]->path);
        self::assertFalse($mail->attachments[0]->isEmbedded());
        self::assertTrue($mail->attachments[1]->isEmbedded());
    }

    public function testMailRejectsInvalidAddressString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Mail(to: ['not-an-email'], subject: 'S', body: 'B');
    }

    public function testMailRejectsEmptyRecipients(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Mail(to: [], subject: 'S', body: 'B');
    }
}
