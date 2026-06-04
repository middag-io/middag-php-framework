<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Resolver\FormRequestResolver;

/**
 * Contract for self-validating request objects.
 *
 * Implementers run their validation in `validate()` and throw
 * `MiddagValidationException` when the incoming request is invalid.
 * `FormRequestResolver` invokes `validate()` at resolution time — before the
 * controller action runs — so the action only ever sees a valid request.
 *
 * The canonical implementation is {@see AbstractFormRequest}, which adds
 * declarative `rules()` and exposes `validated()` for the passing data;
 * `validated()` is not part of this contract.
 *
 * @api
 *
 * @see AbstractFormRequest
 * @see FormRequestResolver
 */
interface FormRequestInterface
{
    /**
     * Validate the incoming request, throwing on failure.
     *
     * @throws MiddagValidationException when validation fails
     */
    public function validate(): void;
}
