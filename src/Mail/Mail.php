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

use InvalidArgumentException;
use Middag\Framework\Mail\Contract\MailerInterface;

/**
 * Immutable mail message value object handed to a {@see MailerInterface}.
 *
 * Host-agnostic on purpose: it carries only the fields every platform mailer
 * needs (recipients, subject, text body) plus the common optionals (HTML body,
 * explicit sender, cc/bcc, attachment paths). The adapter translates these onto
 * the host sender's argument shape.
 *
 * @api
 */
final readonly class Mail
{
    /**
     * @param list<string> $to          recipient addresses (at least one)
     * @param list<string> $cc          carbon-copy addresses
     * @param list<string> $bcc         blind carbon-copy addresses
     * @param list<string> $attachments absolute file paths to attach
     *
     * @throws InvalidArgumentException when no recipient is given
     */
    public function __construct(
        public array $to,
        public string $subject,
        public string $body,
        public ?string $htmlBody = null,
        public ?string $from = null,
        public array $cc = [],
        public array $bcc = [],
        public array $attachments = [],
    ) {
        if ($to === []) {
            throw new InvalidArgumentException('Mail requires at least one recipient.');
        }
    }
}
