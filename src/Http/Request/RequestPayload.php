<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Request;

use JsonException;
use Middag\Framework\Exception\MiddagDomainException;
use Middag\Framework\Http\Resolver\ValidatedDtoResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts the merged input payload from a request (query + body + JSON).
 *
 * The single source of truth for "what counts as request input", shared by
 * {@see AbstractFormRequest} (array + `Assert\Collection` validation) and
 * {@see ValidatedDtoResolver} (typed-DTO
 * hydration), so both validation styles read identical input.
 *
 * A body declared as `application/json` is decoded strictly: malformed JSON
 * raises a {@see MiddagDomainException} (HTTP 400) instead of being silently
 * dropped. An empty body falls back to the query string. For other content
 * types the query string and request body are merged.
 *
 * Blank values are normalised to `null` (see {@see self::nullifyBlanks()}).
 *
 * @internal
 */
final class RequestPayload
{
    /**
     * @return array<string, mixed>
     *
     * @throws MiddagDomainException when the JSON body is present but malformed
     */
    public static function extract(Request $request): array
    {
        return self::nullifyBlanks(self::read($request));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MiddagDomainException when the JSON body is present but malformed
     */
    private static function read(Request $request): array
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            $content = $request->getContent();

            // An absent/whitespace-only body is "no JSON input", not malformed:
            // fall back to the query string so empty posts still reach validation.
            if (trim($content) === '') {
                return $request->query->all();
            }

            try {
                $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $jsonException) {
                throw new MiddagDomainException('Malformed JSON in request body.', $jsonException->getCode(), previous: $jsonException);
            }

            return is_array($json)
                ? array_merge($request->query->all(), $json)
                : $request->query->all();
        }

        return array_merge(
            $request->query->all(),
            $request->request->all(),
        );
    }

    /**
     * Normalise blank string values to `null`, recursively.
     *
     * HTML forms submit an untouched optional field as `""` (present-but-empty),
     * not by omitting it — so `Assert\Optional` (which only skips *absent* keys)
     * and nullable DTO properties would otherwise reject the blank against a
     * `Type`/`Choice`/… constraint. Coercing `""` to `null` lets those optional
     * constraints pass (Symfony treats `null` as valid for them) while required
     * fields still fail via `NotBlank`/`NotNull` (both reject `null`). Mirrors
     * Laravel's `ConvertEmptyStringsToNull`.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function nullifyBlanks(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::nullifyBlanks($value);
            } elseif ($value === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
