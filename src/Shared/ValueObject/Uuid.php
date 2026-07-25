<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\ValueObject;

use JsonSerializable;
use Middag\Framework\Exception\MiddagValidationException;
use Stringable;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * Immutable RFC 4122 (RFC 9562) UUID, in canonical `8-4-4-4-12` string form.
 *
 * Parsing accepts **any** RFC 4122 version (1 through 8) and exposes the detected
 * one via `version()`; the value is normalised to lowercase, as RFC 4122 §3 requires
 * of output, so two UUIDs that differ only in case compare equal as strings and hit
 * the same database key.
 *
 * Generation is **explicit per version** — `v4()` or `v7()`. There is deliberately no
 * `generate()` that picks a version from configuration: which version a key uses is a
 * decision of the code that mints the key, and it changes the physical behaviour of
 * every index that stores it.
 *
 * **Why v7 matters.** A v4 UUID is 122 bits of entropy, so successive keys land at
 * random positions in a B-tree index: every insert dirties a different page, splits
 * happen mid-page, and the index fragments and stops fitting in cache. A v7 UUID is
 * prefixed with a 48-bit Unix-millisecond timestamp, so successive keys are
 * monotonically increasing — inserts append at the right edge of the tree, page
 * splits stay local, and the index keeps its natural time locality (a range scan over
 * "recent rows" becomes sequential). That is what makes v7 the right choice for the
 * `guid`/`uuid` columns of a canonical schema, which are written monotonically and
 * read by recency. Use v4 only when the key must leak nothing about creation time.
 *
 * A malformed value is refused at construction with `MiddagValidationException`
 * (HTTP 422), so an invalid UUID arriving from a route parameter or payload maps to
 * the right status without any extra handling.
 *
 * @api
 */
final readonly class Uuid implements JsonSerializable, Stringable
{
    /**
     * Canonical RFC 4122 form, matched case-insensitively.
     *
     * Stricter than `Symfony\Component\Uid\Uuid::isValid()` on purpose: the version
     * nibble is constrained to `1-8` and the variant nibble to the RFC 4122 `10xx`
     * variant, which rejects the two special values Symfony accepts — the nil UUID
     * (`00000000-0000-0000-0000-000000000000`) and the max UUID (all `f`). Neither
     * identifies anything, so neither is a valid identifier for this VO.
     *
     * The `D` modifier is load-bearing: without it PCRE lets `$` match *before* a
     * final newline, so `"<valid uuid>\n"` would be accepted and then stored with the
     * newline still attached.
     */
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di';

    /**
     * Offset of the version nibble in the canonical form.
     */
    private const VERSION_OFFSET = 14;

    /**
     * How much of a refused value is echoed back in the exception message.
     *
     * A valid UUID is 36 characters, so anything longer is already garbage; capping
     * keeps an oversized or accidental payload (a whole request body, a secret pasted
     * into the wrong field) out of logs and error trackers.
     */
    private const MAX_ECHOED_LENGTH = 48;

    /**
     * The canonical lowercase UUID string.
     */
    public string $value;

    /**
     * @param string $uuid an RFC 4122 UUID of any version (1-8), case-insensitive
     *
     * @throws MiddagValidationException when $uuid is not a canonical RFC 4122 UUID
     */
    public function __construct(string $uuid)
    {
        if (preg_match(self::PATTERN, $uuid) !== 1) {
            throw new MiddagValidationException(
                sprintf('"%s" is not a valid RFC 4122 UUID.', $this->echoable($uuid)),
            );
        }

        $this->value = strtolower($uuid);
    }

    /**
     * The canonical lowercase string form.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Parse an existing UUID string of any RFC 4122 version.
     *
     * @throws MiddagValidationException when $uuid is not a canonical RFC 4122 UUID
     */
    public static function fromString(string $uuid): self
    {
        return new self($uuid);
    }

    /**
     * Mint a random (version 4) UUID.
     *
     * Pure entropy: reveals nothing about creation time, and scatters across a B-tree
     * index. Prefer `v7()` for stored primary/business keys.
     */
    public static function v4(): self
    {
        return new self(SymfonyUuid::v4()->toRfc4122());
    }

    /**
     * Mint a time-ordered (version 7) UUID.
     *
     * Timestamp-prefixed, so successive values sort ascending as plain strings and
     * append to the right edge of a B-tree index instead of fragmenting it.
     */
    public static function v7(): self
    {
        return new self(SymfonyUuid::v7()->toRfc4122());
    }

    /**
     * The RFC 4122 version encoded in the value: 1, 4 and 7 in practice, 1-8 by contract.
     */
    public function version(): int
    {
        return (int) $this->value[self::VERSION_OFFSET];
    }

    /**
     * Serialises to the raw canonical string, not to an object.
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Truncate a refused value to a length that is safe to put in a message.
     */
    private function echoable(string $value): string
    {
        return mb_strlen($value) > self::MAX_ECHOED_LENGTH
            ? mb_substr($value, 0, self::MAX_ECHOED_LENGTH) . '...'
            : $value;
    }
}
