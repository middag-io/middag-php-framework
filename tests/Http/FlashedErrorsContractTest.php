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

use JsonSerializable;
use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Framework\Translation\TranslatableMessage;
use Middag\Ui\Form\FormErrorBags;
use Middag\Ui\Form\FormErrors;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Contract: the validation errors the kernel flashes to the session, as the
 * middleware shares them, match the `errors` slot of the middag-io/ui shared
 * props (`SharedProps.properties.errors` in `schema/page-contract.json`,
 * shipped with the `middag-io/ui` dependency): a `FormErrors` map, or a
 * `FormErrorBags` map when the request named an error bag.
 *
 * @internal
 */
#[CoversClass(HttpKernel::class)]
final class FlashedErrorsContractTest extends TestCase
{
    private const string UI_SCHEMA = __DIR__ . '/../../vendor/middag-io/ui/schema/page-contract.json';

    #[Test]
    public function flashedErrorsValidateAgainstTheUiErrorsSlot(): void
    {
        $flash = $this->submitInvalidForm([
            'title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.'),
            'tags' => ['Too many tags', new TranslatableMessage('validation.unique', 'validators', ['limit' => 3], 'Tags must be unique.')],
            'address.zip' => 'Invalid zip',
            '_' => 'The form could not be saved.',
        ]);

        $errors = $flash->pullErrors();

        $this->assertInstanceOf(FormErrors::class, $errors);
        $this->assertSame(
            '{"title":{"message":"This value should not be blank.","key":"validation.not_blank","domain":"validators"},'
            . '"tags":["Too many tags",{"message":"Tags must be unique.","key":"validation.unique","domain":"validators","params":{"limit":3}}],'
            . '"address.zip":"Invalid zip",'
            . '"_":"The form could not be saved."}',
            json_encode($errors, JSON_THROW_ON_ERROR),
        );
        $this->assertMatchesErrorsSlot($errors);
        $this->assertMatchesUiDefinition('FormErrors', $errors);
    }

    #[Test]
    public function errorsFlashedUnderAnInertiaErrorBagValidateAgainstTheUiErrorsSlot(): void
    {
        $flash = $this->submitInvalidForm(
            ['title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.')],
            'createTask',
        );

        $errors = $flash->pullErrors();

        $this->assertInstanceOf(FormErrorBags::class, $errors);
        $this->assertSame(
            '{"createTask":{"title":{"message":"This value should not be blank.","key":"validation.not_blank","domain":"validators"}}}',
            json_encode($errors, JSON_THROW_ON_ERROR),
        );
        $this->assertMatchesErrorsSlot($errors);
        $this->assertMatchesUiDefinition('FormErrorBags', $errors);
    }

    #[Test]
    public function anEmptyErrorMapValidatesAgainstTheUiErrorsSlot(): void
    {
        $errors = $this->submitInvalidForm([])->pullErrors();

        $this->assertSame('{}', json_encode($errors, JSON_THROW_ON_ERROR));
        $this->assertMatchesErrorsSlot($errors);
    }

    #[Test]
    public function theMiniValidatorRejectsAMalformedErrorsSlot(): void
    {
        $schema = $this->uiSchema();
        $slot = $schema['$defs']['SharedProps']['properties']['errors'];

        $defs = $schema['$defs'];

        $this->assertNotSame([], $this->validate($slot, $this->decode('{"b":{"f":42}}'), $defs, '$'));
        $this->assertNotSame([], $this->validate($slot, $this->decode('{"f":{"message":"m","params":{"a":1}}}'), $defs, '$'));
        $this->assertNotSame([], $this->validate($slot, $this->decode('[]'), $defs, '$'));
        $this->assertSame([], $this->validate($slot, $this->decode('{}'), $defs, '$'));

        $fieldMessage = $defs['FieldMessage'];
        $this->assertSame([], $this->validate($fieldMessage, $this->decode('{"message":"m","key":"k","domain":"d","params":{"a":1}}'), $defs, '$'));
        $this->assertNotSame([], $this->validate($fieldMessage, $this->decode('{"message":"m","key":"k"}'), $defs, '$'));
        $this->assertNotSame([], $this->validate($fieldMessage, $this->decode('{"message":"m","key":"k","domain":"d","extra":"x"}'), $defs, '$'));
        $this->assertNotSame([], $this->validate($fieldMessage, $this->decode('{"message":"m","key":"k","domain":"d","params":[]}'), $defs, '$'));
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

    private function assertMatchesErrorsSlot(JsonSerializable $value): void
    {
        $schema = $this->uiSchema();
        $this->assertArrayHasKey('errors', $schema['$defs']['SharedProps']['properties']);

        $violations = $this->validate(
            $schema['$defs']['SharedProps']['properties']['errors'],
            $this->decode(json_encode($value, JSON_THROW_ON_ERROR)),
            $schema['$defs'],
            '$',
        );

        $this->assertSame([], $violations, 'Flashed errors do not match the ui SharedProps errors slot.');
    }

    private function assertMatchesUiDefinition(string $definition, JsonSerializable $value): void
    {
        $schema = $this->uiSchema();
        $this->assertArrayHasKey($definition, $schema['$defs']);

        $violations = $this->validate(
            $schema['$defs'][$definition],
            $this->decode(json_encode($value, JSON_THROW_ON_ERROR)),
            $schema['$defs'],
            '$',
        );

        $this->assertSame([], $violations, 'Flashed errors do not match the ui ' . $definition . ' schema.');
    }

    /**
     * @return array{$defs: array<string, mixed>}
     */
    private function uiSchema(): array
    {
        $this->assertFileExists(self::UI_SCHEMA);
        $schema = json_decode((string) file_get_contents(self::UI_SCHEMA), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('$defs', $schema);

        return $schema;
    }

    /**
     * Decode keeping JSON objects as objects, so `{}` and `[]` stay distinct.
     */
    private function decode(string $json): mixed
    {
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Minimal validator for the keywords the errors slot definitions use:
     * $ref, anyOf, oneOf, type (object|array|string), properties, required,
     * additionalProperties (schema or boolean), items. Objects are decoded
     * JSON objects ({@see stdClass}), arrays are lists.
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

        if (isset($node['anyOf'])) {
            foreach ($node['anyOf'] as $branch) {
                if ($this->validate($branch, $value, $defs, $path) === []) {
                    return [];
                }
            }

            return [$path . ': expected at least one anyOf branch to match'];
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
        if (!is_array($value)) {
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
        if (!$value instanceof stdClass) {
            return [$path . ': expected object'];
        }

        $members = get_object_vars($value);
        $violations = [];

        foreach ($node['required'] ?? [] as $required) {
            if (!array_key_exists($required, $members)) {
                $violations[] = $path . ': missing required ' . $required;
            }
        }

        $properties = $node['properties'] ?? [];
        $additional = $node['additionalProperties'] ?? true;

        foreach ($members as $key => $item) {
            $itemPath = $path . '.' . $key;

            if (array_key_exists($key, $properties)) {
                $violations = [...$violations, ...$this->validate($properties[$key], $item, $defs, $itemPath)];
            } elseif ($additional === false) {
                $violations[] = $itemPath . ': unexpected property';
            } elseif (is_array($additional)) {
                $violations = [...$violations, ...$this->validate($additional, $item, $defs, $itemPath)];
            }
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
