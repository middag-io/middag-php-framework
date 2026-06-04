<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Mail\Contract;

use Middag\Framework\Mail\Mail;
use Middag\Framework\Mail\NullMailer;

/**
 * Infrastructure port for sending mail, so domain/use-case code depends on a
 * thin contract instead of a host mail API.
 *
 * Deliberately NOT symfony/mailer: the framework only declares the seam and a
 * value object; host adapters map a {@see Mail} onto the platform's native
 * sender (Moodle `email_to_user()`, WordPress `wp_mail()`), and the product may
 * bind a real transport (SMTP, API) when running standalone.
 *
 * Default OSS impl: {@see NullMailer} (discards — standalone has no MTA).
 *
 * @api
 */
interface MailerInterface
{
    /**
     * Send the message.
     *
     * Implementations throw on an unrecoverable transport failure; a no-op
     * default ({@see NullMailer}) never throws.
     */
    public function send(Mail $mail): void;
}
