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
 * Immutable mail attachment: a file by absolute path with an optional display
 * name, MIME type, and content id.
 *
 * A non-null {@see self::$contentId} marks the attachment as an embedded
 * (inline) part: the HTML body references it as `cid:<contentId>` — the piece
 * advanced HTML templates need for logos/images. Adapters map this onto the
 * host's inline-attachment mechanism (symfony/mime `embedFromPath`, Moodle
 * attachment args, `wp_mail` multipart).
 *
 * @api
 */
final readonly class Attachment
{
    /**
     * @param string      $path      absolute path of the file to attach
     * @param null|string $name      filename shown to the recipient (defaults to basename)
     * @param null|string $mimeType  explicit MIME type (adapters may sniff when null)
     * @param null|string $contentId inline content id (`cid:` reference) — null = regular attachment
     *
     * @throws InvalidArgumentException when the path is empty
     */
    public function __construct(
        public string $path,
        public ?string $name = null,
        public ?string $mimeType = null,
        public ?string $contentId = null,
    ) {
        if ($this->path === '') {
            throw new InvalidArgumentException('Attachment requires a non-empty path.');
        }
    }

    /**
     * Shorthand for an embedded (inline) image referenced from the HTML body
     * as `cid:<contentId>`.
     */
    public static function embedded(string $path, string $contentId, ?string $mimeType = null): self
    {
        return new self($path, null, $mimeType, $contentId);
    }

    /**
     * Filename shown to the recipient.
     */
    public function filename(): string
    {
        return $this->name ?? basename($this->path);
    }

    /**
     * True when this attachment is an inline `cid:` part.
     */
    public function isEmbedded(): bool
    {
        return $this->contentId !== null;
    }
}
