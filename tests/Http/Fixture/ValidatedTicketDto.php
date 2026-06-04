<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture;

use Middag\Framework\Form\Attribute\Field;
use Middag\Ui\Shared\Enum\FieldType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Plain DTO fixture for the `#[ValidatedDto]` path.
 *
 * Proves the design claim: the same class carries `#[Field]` (form schema) and
 * `#[Assert]` (validation) on its properties, with no framework base — snake_case
 * request input (`customer_id`, `agent_id`) maps onto the camelCase properties.
 */
final class ValidatedTicketDto
{
    #[Field(type: FieldType::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $subject;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
    public string $priority;

    #[Assert\NotNull]
    #[Assert\Positive]
    public int $customerId;

    public ?int $agentId = null;
}
