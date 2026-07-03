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

use Middag\Framework\Form\Field\TextField;
use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Framework\Form\Renderer\InertiaRenderer;
use Middag\Ui\Block\Section;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\FormState;
use Middag\Ui\Form\Group;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Shared\ValueObject\RendererOutput;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the renderer to the ui 0.5.0 PROPS output medium (INERTIA
 * case removed) and the section/group/field node shape.
 *
 * @internal
 */
#[CoversClass(InertiaRenderer::class)]
final class InertiaRendererTest extends TestCase
{
    #[Test]
    public function targetIsPropsMedium(): void
    {
        self::assertSame(RenderTarget::PROPS, InertiaRenderer::target());
    }

    #[Test]
    public function renderProducesPropsPayloadWithTypedNodes(): void
    {
        $form = new RendererFakeForm(
            [
                Section::of('details')
                    ->label(Translatable::of('sec_details', 'core'))
                    ->fields(new TextField('title')),
                Group::of('row')->fields(new TextField('a'), new TextField('b')),
                new TextField('loose'),
            ],
            new FormState(['title' => 'Hi'], ['title' => 'bad'], true),
        );

        $output = (new InertiaRenderer(new InertiaFieldMapper()))->render($form);

        self::assertInstanceOf(RendererOutput::class, $output);
        self::assertSame(RenderTarget::PROPS, $output->target);

        $schema = $output->props['schema'];
        self::assertCount(3, $schema);

        self::assertSame('section', $schema[0]['kind']);
        self::assertSame('details', $schema[0]['id']);
        self::assertSame(['key' => 'sec_details', 'domain' => 'core'], $schema[0]['label']);
        self::assertCount(1, $schema[0]['children']);
        self::assertSame('field', $schema[0]['children'][0]['kind']);

        self::assertSame('group', $schema[1]['kind']);
        self::assertSame('row', $schema[1]['id']);
        self::assertCount(2, $schema[1]['children']);

        self::assertSame('field', $schema[2]['kind']);
        self::assertSame('loose', $schema[2]['key'], 'field nodes carry a node-level key (lib reads field.key)');
        self::assertArrayHasKey('component', $schema[2]);
        self::assertArrayHasKey('props', $schema[2]);

        self::assertSame(['title' => 'Hi'], $output->props['values']);
        self::assertSame(['title' => 'bad'], $output->props['errors']);
        self::assertSame([], $output->props['meta']);
    }

    #[Test]
    public function fieldDefaultsSeedInitialValuesUnderSubmittedState(): void
    {
        // The lib binds the form to `values`; a fresh form must carry its field
        // defaults there, while any submitted/hydrated value overrides its default.
        $form = new RendererFakeForm(
            [
                (new TextField('greeting'))->default('hello'),
                (new TextField('title'))->default('ignored'),
            ],
            new FormState(['title' => 'Hi'], [], false),
        );

        $output = (new InertiaRenderer(new InertiaFieldMapper()))->render($form);

        self::assertSame(['greeting' => 'hello', 'title' => 'Hi'], $output->props['values']);
    }
}

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
