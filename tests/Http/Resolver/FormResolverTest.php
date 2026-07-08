<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Resolver;

use Middag\Framework\Http\Resolver\FormResolver;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FormResolver::class)]
final class FormResolverTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueForFormInterfaceSubclass(): void
    {
        $resolver = new FormResolver($this->container([]), Request::create('/foo'));
        $param = $this->paramByName('controller_method', 'form');

        $this->assertTrue($resolver->supports($param));
    }

    #[Test]
    public function supportsReturnsFalseForNonFormType(): void
    {
        $resolver = new FormResolver($this->container([]), Request::create('/foo'));
        $param = $this->paramByName('controller_method', 'plain');

        $this->assertFalse($resolver->supports($param));
    }

    #[Test]
    public function resolveOnGetReturnsFormWithoutHydrateOrValidate(): void
    {
        $form = new FakeForm();
        $resolver = new FormResolver(
            $this->container([FakeForm::class => $form]),
            Request::create('/foo', 'GET')
        );

        $param = $this->paramByName('controller_method', 'form');
        $result = $resolver->resolve($param, []);

        $this->assertSame($form, $result);
        $this->assertSame(0, $form->hydrateCalls);
        $this->assertSame(0, $form->validateCalls);
    }

    #[Test]
    public function resolveOnPostHydratesAndValidates(): void
    {
        $form = new FakeForm();
        $request = Request::create('/foo', 'POST', ['name' => 'invoice-1']);
        $resolver = new FormResolver(
            $this->container([FakeForm::class => $form]),
            $request
        );

        $param = $this->paramByName('controller_method', 'form');
        $resolver->resolve($param, []);

        $this->assertSame(['name' => 'invoice-1'], $form->lastInput);
        $this->assertSame(1, $form->hydrateCalls);
        $this->assertSame(1, $form->validateCalls);
        $this->assertTrue($form->isSubmittedAndValid());
    }

    #[Test]
    public function resolveOnDeleteHydratesAndValidates(): void
    {
        $form = new FakeForm();
        $request = Request::create('/foo', 'DELETE', ['id' => '5']);
        $resolver = new FormResolver(
            $this->container([FakeForm::class => $form]),
            $request
        );

        $param = $this->paramByName('controller_method', 'form');
        $resolver->resolve($param, []);

        $this->assertSame(['id' => '5'], $form->lastInput);
        $this->assertSame(1, $form->hydrateCalls);
        $this->assertSame(1, $form->validateCalls);
    }

    private function paramByName(string $method, string $paramName): ReflectionParameter
    {
        foreach ((new ReflectionMethod(FakeFormController::class, $method))->getParameters() as $param) {
            if ($param->getName() === $paramName) {
                return $param;
            }
        }

        throw new RuntimeException('parameter not found');
    }

    /** @param array<string, object> $services */
    private function container(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, object> $services */
            public function __construct(private readonly array $services) {}

            public function get(string $id): object
            {
                return $this->services[$id]
                    ?? throw new RuntimeException('service not bound: ' . $id);
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}

final class FakeForm implements FormInterface
{
    public int $hydrateCalls = 0;

    public int $validateCalls = 0;

    /** @var array<string, mixed> */
    public array $lastInput = [];

    public function schema(): array
    {
        return [];
    }

    public function hydrate(array $input): void
    {
        ++$this->hydrateCalls;
        $this->lastInput = $input;
    }

    public function validate(): void
    {
        ++$this->validateCalls;
    }

    public function isSubmittedAndValid(): bool
    {
        return $this->hydrateCalls > 0 && $this->validateCalls > 0;
    }

    public function validated(): array
    {
        return $this->lastInput;
    }

    public function errors(): array
    {
        return [];
    }

    public function state(): FormState
    {
        return new FormState();
    }
}

final class FakeFormController
{
    public function controller_method(FakeForm $form, string $plain = ''): void {}
}
