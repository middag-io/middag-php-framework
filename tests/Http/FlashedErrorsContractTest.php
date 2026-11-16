<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Framework\Translation\TranslatableMessage;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Contract: the validation errors the kernel flashes to the session match the
 * `FormErrors` / `FieldError` definitions of the middag-io/ui wire contract
 * (`schema/page-contract.json`, shipped with the `middag-io/ui` dependency).
 *
 * @internal
 */
#[CoversClass(HttpKernel::class)]
final class FlashedErrorsContractTest extends TestCase
{
    private const string UI_SCHEMA = __DIR__ . '/../../vendor/middag-io/ui/schema/page-contract.json';

    #[Test]
    public function flashedErrorsValidateAgainstTheUiFormErrorsSchema(): void
    {
        $flash = $this->submitInvalidForm([
            'title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.'),
            'tags' => ['Too many tags', new TranslatableMessage('validation.unique', 'validators', [], 'Tags must be unique.')],
            'address.zip' => 'Invalid zip',
            '_' => 'The form could not be saved.',
        ]);

        $errors = $flash->pullErrors();

        $this->assertSame([
            'title' => 'This value should not be blank.',
            'tags' => ['Too many tags', 'Tags must be unique.'],
            'address.zip' => 'Invalid zip',
            '_' => 'The form could not be saved.',
        ], $errors);
        $this->assertMatchesUiDefinition('FormErrors', $errors);
    }

    #[Test]
    public function eachInertiaErrorBagHoldsAUiFormErrorsMap(): void
    {
        $flash = $this->submitInvalidForm(
            ['title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.')],
            'createTask',
        );

        $errors = $flash->pullErrors();

        $this->assertSame(['createTask' => ['title' => 'This value should not be blank.']], $errors);
        $this->assertMatchesUiDefinition('FormErrors', $errors['createTask']);
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function submitInvalidForm(array $errors, ?string $errorBag = null): FlashBag
    {
        $flash = new FlashBag(new ArraySession());

        $routes = new RouteCollection();
        $routes->add('store', (new Route('/tasks', ['_controller' => static function () use ($errors): never {
            throw new MiddagValidationException('Validation failed', $errors);
        }]))->setMethods(['POST']));

        $request = (new ServerRequest('POST', '/tasks'))->withHeader('Referer', '/tasks/create');

        if ($errorBag !== null) {
            $request = $request->withHeader('X-Inertia-Error-Bag', $errorBag);
        }

        $response = $this->kernel($routes, $flash)->handle($request);
        $this->assertSame(303, $response->getStatusCode());

        return $flash;
    }

    private function assertMatchesUiDefinition(string $definition, mixed $value): void
    {
        $this->assertFileExists(self::UI_SCHEMA);
        $schema = json_decode((string) file_get_contents(self::UI_SCHEMA), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($schema);
        $this->assertArrayHasKey($definition, $schema['$defs']);

        $violations = $this->validate($schema['$defs'][$definition], $value, $schema['$defs'], '$');

        $this->assertSame([], $violations, 'Flashed errors do not match the ui ' . $definition . ' schema.');
    }

    /**
     * Minimal validator for the keywords the FormErrors/FieldError definitions
     * use: $ref, oneOf, type (object|array|string), additionalProperties, items.
     *
     * @param array<string, mixed> $node
     * @param array<string, mixed> $defs
     *
     * @return list<string>
     */
    private function validate(array $node, mixed $value, array $defs, string $path): array
    {
        if (isset($node['$ref'])) {
            $name = substr((string) $node['$ref'], strlen('#/$defs/'));
            $this->assertArrayHasKey($name, $defs, 'Unknown $ref ' . $node['$ref']);

            return $this->validate($defs[$name], $value, $defs, $path);
        }

        if (isset($node['oneOf'])) {
            $matches = array_filter(
                $node['oneOf'],
                fn (array $branch): bool => $this->validate($branch, $value, $defs, $path) === [],
            );

            return count($matches) === 1 ? [] : [$path . ': expected exactly one oneOf branch to match'];
        }

        return match ($node['type'] ?? null) {
            'string' => is_string($value) ? [] : [$path . ': expected string'],
            'array' => $this->validateList($node, $value, $defs, $path),
            'object' => $this->validateObject($node, $value, $defs, $path),
            default => [$path . ': unsupported schema node'],
        };
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $defs
     *
     * @return list<string>
     */
    private function validateList(array $node, mixed $value, array $defs, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [$path . ': expected array'];
        }

        $violations = [];

        foreach ($value as $index => $item) {
            $violations = [...$violations, ...$this->validate($node['items'], $item, $defs, $path . '[' . $index . ']')];
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $defs
     *
     * @return list<string>
     */
    private function validateObject(array $node, mixed $value, array $defs, string $path): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            return [$path . ': expected object'];
        }

        $violations = [];

        foreach ($value as $key => $item) {
            $violations = [...$violations, ...$this->validate($node['additionalProperties'], $item, $defs, $path . '.' . $key)];
        }

        return $violations;
    }

    private function kernel(RouteCollection $routes, FlashBag $flash): HttpKernel
    {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $this->container($flash),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
    }

    private function container(FlashBag $flash): ContainerInterface
    {
        return new readonly class($flash) implements ContainerInterface {
            public function __construct(
                private FlashBag $flash,
            ) {}

            public function get(string $id): mixed
            {
                if ($id === FlashBag::class) {
                    return $this->flash;
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                return $id === FlashBag::class;
            }
        };
    }
}
