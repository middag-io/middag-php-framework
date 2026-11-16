<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form\Contract;

use Middag\Framework\Form\AbstractForm;
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Form\Contract\FieldInterface;

/**
 * Validates submitted form values against a form schema.
 *
 * The collaborator {@see AbstractForm} receives in its constructor. The
 * container binds this contract to the framework's default validator; a host
 * that binds its own implementation before service discovery keeps it.
 *
 * @api
 */
interface FormValidatorInterface
{
    /**
     * Validate the given values against the schema.
     *
     * @param array<int, FieldInterface|LayoutElementInterface> $schema
     * @param array<string, mixed>                              $values
     *
     * @return array<string, string|string[]> per-field errors: one message, or a
     *                                        list when several rules fail; empty when valid
     */
    public function validate(array $schema, array $values): array;
}
