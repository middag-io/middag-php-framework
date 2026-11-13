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

use InvalidArgumentException;
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
     * Length of a visual id, in hex characters — the default and the ceiling, both
     * for the same reason.
     *
     * Twelve is the whole final group of the canonical form: 48 random bits in v4
     * and in v7 alike (see {@see self::visualId()}), and the point past which a tail
     * slice would swallow the `-` separator and stop being a literal substring of
     * the value.
     */
    public const VISUAL_ID_LENGTH = 12;

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
     * A short id to show a human: the last `$length` hex characters of the value.
     *
     * **Why the tail.** A v7 UUID is timestamp-prefixed, so two ids minted in the
     * same millisecond share everything up to the random part — a head slice would
     * print the same string for different rows. The last 12 characters are the
     * whole final group, 48 random bits in v4 and in v7 alike, so the helper reads
     * the same way whichever version the column stores.
     *
     * **It stays a literal substring** of the canonical value (hence the 12-character
     * ceiling, which is where the final group ends). A support agent can take what
     * the screen shows and find the row with `LIKE '%-<visual id>'`, or grep a log for
     * it, with no reverse mapping and no second column to keep in sync.
     *
     * **Collisions are expected, not prevented.** 48 bits over 10^4 rows is ~1.8e-7,
     * over 10^6 rows ~0.18%: fine for reading an id aloud or quoting it in a ticket,
     * which is what this is for. A screen that *searches* by visual id must therefore
     * accept more than one hit. Past ~10^5 rows, index an explicit tail column instead
     * of scanning with a leading-wildcard LIKE.
     *
     * **Not an identifier.** The value is the identifier; this is a display helper.
     * Never store it as a key, and never use it where a business-facing counter
     * belongs (an order, a ticket, a certificate — the number a person quotes back
     * gets its own per-type sequence). Technical reference — support, logs, audit,
     * licensing — is what this serves.
     *
     * @param int $length 1 to 12 hex characters
     *
     * @throws InvalidArgumentException when $length is outside 1-12 (a programming
     *                                  error: the argument is code's, never the user's)
     */
    public function visualId(int $length = self::VISUAL_ID_LENGTH): string
    {
        self::assertVisualIdLength($length);

        return substr($this->value, -$length);
    }

    /**
     * The same display helper for a column that is *not* guaranteed to hold a UUID.
     *
     * Some `guid` columns carry a hash, a foreign key from a third party, or nothing
     * at all. This never refuses the value: a canonical UUID is lowercased and sliced
     * like {@see self::visualId()}, anything else has its last `$length` characters
     * taken as they are, and a null or blank value yields `null` so a template can
     * print it without a guard. Surrounding whitespace is trimmed first, so a stored
     * trailing newline does not become part of what is shown.
     *
     * The promise about UUIDs does not carry over to an arbitrary string: the tail of
     * a sequential or structured id can repeat across rows. Treat the result as a
     * label, and keep the `LIKE '%-<visual id>'` lookup for columns that really do
     * hold UUIDs.
     *
     * @param int $length 1 to 12 characters
     *
     * @throws InvalidArgumentException when $length is outside 1-12 (the argument is
     *                                  code's; the *value* never makes this throw)
     */
    public static function tryVisualId(?string $guid, int $length = self::VISUAL_ID_LENGTH): ?string
    {
        self::assertVisualIdLength($length);

        if ($guid === null) {
            return null;
        }

        $guid = trim($guid);

        if ($guid === '') {
            return null;
        }

        if (preg_match(self::PATTERN, $guid) === 1) {
            return substr(strtolower($guid), -$length);
        }

        return mb_substr($guid, -$length);
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
     * @throws InvalidArgumentException when $length cannot produce a visual id
     */
    private static function assertVisualIdLength(int $length): void
    {
        if ($length < 1 || $length > self::VISUAL_ID_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'A visual id is 1 to %d characters, got %d.',
                self::VISUAL_ID_LENGTH,
                $length,
            ));
        }
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
