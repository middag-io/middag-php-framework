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
use Middag\Ui\Form\FieldMessage;

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
     * Each field maps to one message or a list when several rules fail; a
     * message is a string or a structured {@see FieldMessage} (or its
     * serialized array). Empty when valid.
     *
     * @return array<string, array{message: string, key: string, domain: string, params?: array<string, mixed>}|FieldMessage|list<array{message: string, key: string, domain: string, params?: array<string, mixed>}|FieldMessage|string>|string>
     */
    public function validate(array $schema, array $values): array;
}
