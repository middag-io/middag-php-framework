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
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Block\Section;
use Middag\Ui\Form\FieldMessage;
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
        self::assertSame(RenderTarget::Props, InertiaRenderer::target());
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
        self::assertSame(RenderTarget::Props, $output->target);

        $schema = $this->wire($output->props['schema']);
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
    public function structuredErrorsReachThePropsInTheFieldMessageShape(): void
    {
        $form = new RendererFakeForm(
            [new TextField('title')],
            new FormState([], ['title' => new FieldMessage('Too short.', 'validation.length.too_short', 'validators', ['limit' => 3])], true),
        );

        $output = (new InertiaRenderer(new InertiaFieldMapper()))->render($form);

        self::assertSame(
            '{"title":{"message":"Too short.","key":"validation.length.too_short","domain":"validators","params":{"limit":3}}}',
            json_encode($output->props['errors'], JSON_THROW_ON_ERROR),
        );
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

    #[Test]
    public function unknownLayoutElementSerializesAsAGenericGroupNode(): void
    {
        // A layout element that is neither Section nor Group falls through to the
        // generic LayoutElementInterface branch and is emitted as a group node.
        $custom = new class implements LayoutElementInterface {
            public function id(): string
            {
                return 'custom';
            }

            /** @return array<int, TextField> */
            public function children(): array
            {
                return [new TextField('inner')];
            }
        };

        $form = new RendererFakeForm([$custom], new FormState([], [], false));

        $schema = $this->wire((new InertiaRenderer(new InertiaFieldMapper()))->render($form)->props['schema']);

        self::assertCount(1, $schema);
        self::assertSame('group', $schema[0]['kind']);
        self::assertSame('custom', $schema[0]['id']);
        self::assertCount(1, $schema[0]['children']);
        self::assertSame('field', $schema[0]['children'][0]['kind']);
    }

    /**
     * Schema nodes are value objects; assert against their serialized wire.
     *
     * @return array<int, array<string, mixed>>
     */
    private function wire(mixed $schema): array
    {
        return json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
