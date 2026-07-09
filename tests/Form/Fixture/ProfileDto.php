<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form\Fixture;

use Middag\Framework\Form\Attribute\Field;
use Middag\Ui\Shared\Enum\FieldType;

/**
 * DTO that declares its form fields via #[Field].
 *
 * @internal
 */
final class ProfileDto
{
    #[Field(label: 'profile.name', required: true)]
    public string $fullName = '';

    #[Field(type: FieldType::Email, label: 'profile.email')]
    public string $email = '';

    #[Field]
    public int $age = 0;

    #[Field]
    public bool $active = false;

    public string $internal = '';
}
