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
}
