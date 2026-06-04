<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Mail;

use Middag\Framework\Mail\Contract\MailerInterface;

/**
 * Default OSS mailer: discards the message.
 *
 * Standalone (no host) has no mail transport, so the safe default is a no-op
 * named to make that obvious — nothing is sent and nothing throws. Bind a host
 * adapter (Moodle `email_to_user`, WordPress `wp_mail`) or a real transport
 * (SMTP/API) in production to actually deliver mail.
 *
 * @api
 */
final readonly class NullMailer implements MailerInterface
{
    public function send(Mail $mail): void
    {
        // Intentional no-op: standalone has no MTA. Swap for a real mailer.
    }
}
