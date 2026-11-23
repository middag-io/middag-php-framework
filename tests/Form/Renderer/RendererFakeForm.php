<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form\Renderer;

use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\FormState;

/**
 * Minimal FormInterface double carrying a fixed schema and state.
 *
 * @internal
 */
final readonly class RendererFakeForm implements FormInterface
{
    /** @param array<int, mixed> $schema */
    public function __construct(private array $schema, private FormState $formState) {}

    /** @return array<int, mixed> */
    public function schema(): array
    {
        return $this->schema;
    }

    /** @param array<string, mixed> $input */
    public function hydrate(array $input): void {}

    public function validate(): void {}

    public function isSubmittedAndValid(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return [];
    }

    /** @return array<string, string|string[]> */
    public function errors(): array
    {
        return $this->formState->errors();
    }

    public function state(): FormState
    {
        return $this->formState;
    }
}
