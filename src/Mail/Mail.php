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
 * Host-agnostic on purpose: it carries only what every platform mailer needs
 * (recipients, subject, text body) plus the common optionals — HTML body,
 * explicit sender/reply-to, cc/bcc, attachments (including embedded `cid:`
 * parts for HTML templates, see {@see Attachment}).
 *
 * Ergonomics mirror symfony/mime: every address accepts a plain string
 * (`'jane@example.org'`, `'Jane <jane@example.org>'`) or an {@see Address};
 * attachments accept a path string or an {@see Attachment}. Strings are
 * normalised to the value objects at construction, so adapters always read a
 * uniform shape.
 *
 * Template rendering stays OUT of this object: render first (Twig/Mustache/ui),
 * pass the result as {@see self::$htmlBody}.
 *
 * @api
 */
final readonly class Mail
{
    /** @var list<Address> */
    public array $to;

    public ?Address $from;

    public ?Address $replyTo;

    /** @var list<Address> */
    public array $cc;

    /** @var list<Address> */
    public array $bcc;

    /** @var list<Attachment> */
    public array $attachments;

    /**
     * @param list<Address|string>    $to          recipients (at least one)
     * @param list<Address|string>    $cc          carbon-copy recipients
     * @param list<Address|string>    $bcc         blind carbon-copy recipients
     * @param list<Attachment|string> $attachments attachments — a path string or an {@see Attachment} (use {@see Attachment::embedded()} for `cid:` parts)
     *
     * @throws InvalidArgumentException when no recipient is given or an address string is invalid
     */
    public function __construct(
        array $to,
        public string $subject,
        public string $body,
        public ?string $htmlBody = null,
        Address|string|null $from = null,
        Address|string|null $replyTo = null,
        array $cc = [],
        array $bcc = [],
        array $attachments = [],
    ) {
        if ($to === []) {
            throw new InvalidArgumentException('Mail requires at least one recipient.');
        }

        $this->to = $this->addresses($to);
        $this->from = \is_string($from) ? Address::parse($from) : $from;
        $this->replyTo = \is_string($replyTo) ? Address::parse($replyTo) : $replyTo;
        $this->cc = $this->addresses($cc);
        $this->bcc = $this->addresses($bcc);
        $this->attachments = array_map(
            static fn (Attachment|string $attachment): Attachment => \is_string($attachment) ? new Attachment($attachment) : $attachment,
            $attachments,
        );
    }

    /**
     * @param list<Address|string> $addresses
     *
     * @return list<Address>
     */
    private function addresses(array $addresses): array
    {
        return array_map(
            static fn (Address|string $address): Address => \is_string($address) ? Address::parse($address) : $address,
            $addresses,
        );
    }
}
