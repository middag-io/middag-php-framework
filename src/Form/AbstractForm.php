<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form;

use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\FormState;

/**
 * Base form class — implements the full lifecycle defined by FormInterface.
 *
 * Subclasses must implement schema() to declare fields.
 * The resolver chain hydrates and validates before the controller executes.
 *
 * Public override point: hosts (Moodle/WP adapters and standalone apps alike)
 * subclass this to declare a form — same role as the other Abstract* bases
 * ({@see AbstractController}, etc.). The
 * `FormValidator` collaborator is injected by the container.
 *
 * @api
 */
abstract class AbstractForm implements FormInterface
{
    private FormState $state;

    public function __construct(protected readonly FormValidator $validator)
    {
        $this->state = new FormState();
    }

    /**
     * {@inheritdoc}
     *
     * @return array<int, FieldInterface|LayoutElementInterface>
     */
    abstract public function schema(): array;

    /** {@inheritdoc} */
    public function hydrate(array $input): void
    {
        $this->state = $this->state->withValues($input);
    }

    /** {@inheritdoc} */
    public function validate(): void
    {
        $errors = $this->validator->validate($this->schema(), $this->state->values());
        $this->state = $this->state->withErrors($errors);
    }

    /** {@inheritdoc} */
    public function isSubmittedAndValid(): bool
    {
        return $this->state->isSubmitted() && $this->state->errors() === [];
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->state->values();
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, string|string[]>
     */
    public function errors(): array
    {
        return $this->state->errors();
    }

    /** {@inheritdoc} */
    public function state(): FormState
    {
        return $this->state;
    }
}
