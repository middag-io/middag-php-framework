<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Attribute;

use Attribute;
use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Form\Attribute\Field;
use Middag\Framework\Http\Request\AbstractFormRequest;
use Middag\Framework\Http\Resolver\ValidatedDtoResolver;

/**
 * Marks a controller parameter for typed-DTO request mapping.
 *
 * The parameter's type must be a plain class whose properties carry Symfony
 * `#[Assert\*]` constraints (and, optionally, the framework's
 * {@see Field} for the form schema — one DTO
 * as the single source of truth for both shape and validation).
 * {@see ValidatedDtoResolver} reads the request
 * input (query + body + JSON), hydrates it into the typed object (snake_case
 * input keys map to camelCase properties, scalar types are coerced), validates
 * the object, and injects it — throwing
 * {@see MiddagValidationException} (HTTP 422) with a
 * `field => message(s)` map on failure, before the action runs.
 *
 * This is the typed-object counterpart to {@see AbstractFormRequest}'s
 * `rules()`-array validation; the DTO stays a plain, reusable class (no
 * framework base), so the same class also works as a form-schema source, a
 * validation target, or a command payload. Optional inputs should be nullable
 * properties with a default (e.g. `public ?int $agentId = null`) so an absent
 * value is a clean null rather than an uninitialized-property error.
 *
 * ```php
 * use Middag\Framework\Http\Attribute\ValidatedDto;
 *
 * public function store(#[ValidatedDto] TicketRequest $ticket): Response
 * {
 *     // $ticket is hydrated + validated; typed property access from here.
 * }
 * ```
 *
 * @api
 *
 * @see ValidatedDtoResolver
 * @see Field
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ValidatedDto {}
