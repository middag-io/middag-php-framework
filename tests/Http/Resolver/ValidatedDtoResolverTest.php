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

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Attribute\ValidatedDto;
use Middag\Framework\Http\Resolver\ValidatedDtoResolver;
use Middag\Framework\Tests\Http\Fixture\ValidatedTicketDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ValidatedDtoResolver::class)]
final class ValidatedDtoResolverTest extends TestCase
{
    #[Test]
    public function supportsAnnotatedClassParameter(): void
    {
        $resolver = new ValidatedDtoResolver(Request::create('/'));

        self::assertTrue($resolver->supports($this->param('store', 'ticket')));
    }

    #[Test]
    public function doesNotSupportUnannotatedParameter(): void
    {
        $resolver = new ValidatedDtoResolver(Request::create('/'));

        self::assertFalse($resolver->supports($this->param('store', 'plain')));
    }

    #[Test]
    public function doesNotSupportAnnotatedBuiltin(): void
    {
        $resolver = new ValidatedDtoResolver(Request::create('/'));

        self::assertFalse($resolver->supports($this->param('store', 'annotatedBuiltin')));
    }

    #[Test]
    public function resolveReturnsHydratedValidatedDto(): void
    {
        $request = Request::create('/api/tickets', 'POST', [
            'subject' => 'Network down',
            'priority' => 'urgent',
            'customer_id' => '3',
        ]);
        $resolver = new ValidatedDtoResolver($request);

        $dto = $resolver->resolve($this->param('store', 'ticket'), []);

        self::assertInstanceOf(ValidatedTicketDto::class, $dto);
        self::assertSame('Network down', $dto->subject);
        self::assertSame(3, $dto->customerId);
    }

    #[Test]
    public function resolveThrowsOnInvalidPayload(): void
    {
        $request = Request::create('/api/tickets', 'POST', ['priority' => 'nope']);
        $resolver = new ValidatedDtoResolver($request);

        $this->expectException(MiddagValidationException::class);

        $resolver->resolve($this->param('store', 'ticket'), []);
    }

    private function param(string $method, string $name): ReflectionParameter
    {
        foreach ((new ReflectionMethod(ValidatedDtoController::class, $method))->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        throw new RuntimeException('parameter not found: ' . $name);
    }
}

final class ValidatedDtoController
{
    public function store(
        #[ValidatedDto]
        ValidatedTicketDto $ticket,
        string $plain = '',
        #[ValidatedDto]
        int $annotatedBuiltin = 0,
    ): void {}
}
