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

/**
 * Immutable mail address: an email with an optional display name.
 *
 * Mirrors the shape of symfony/mime's Address so adapters can map it 1:1 onto
 * the host sender (Moodle user objects, WordPress "Name <email>" headers, an
 * SMTP transport) without re-parsing strings.
 *
 * @api
 */
final readonly class Address
{
    /**
     * @throws InvalidArgumentException when the email is empty, has no "@",
     *                                  contains whitespace, or has an empty
     *                                  local-part or domain
     */
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {
        if (preg_match('/^[^@\s]+@[^@\s]+$/', $this->email) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid email address.', $this->email));
        }
    }

    /**
     * Parse an RFC-style string — `"Jane Doe <jane@example.org>"` or a bare
     * `jane@example.org` — into an Address.
     *
     * @throws InvalidArgumentException when the string is not a valid address
     */
    public static function parse(string $address): self
    {
        if (preg_match('/^\s*(?<name>.*?)\s*<(?<email>[^<>]+)>\s*$/', $address, $matches) === 1) {
            $name = trim($matches['name'], " \t\"'");

            return new self(trim($matches['email']), $name === '' ? null : $name);
        }

        return new self(trim($address));
    }

    /**
     * Render back to the RFC-style string form.
     *
     * Display names containing characters outside RFC 5322 atext (plus space)
     * are emitted as a quoted-string — e.g. `"Doe, Jane" <jane@example.org>`.
     * An unquoted comma would split the address in comma-separated recipient
     * headers (WordPress's wp_mail, SMTP header lists), dropping recipients.
     */
    public function toString(): string
    {
        if ($this->name === null) {
            return $this->email;
        }

        $name = preg_match('/[^A-Za-z0-9 !#$%&\'*+\/=?^_`{|}~.-]/', $this->name) === 1
            ? '"' . addcslashes($this->name, '"\\') . '"'
            : $this->name;

        return sprintf('%s <%s>', $name, $this->email);
    }
}
