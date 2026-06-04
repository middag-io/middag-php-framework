<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Request;

use Middag\Framework\Exception\MiddagDomainException;
use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Request\AbstractFormRequest;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[CoversClass(AbstractFormRequest::class)]
final class AbstractFormRequestTest extends TestCase
{
    #[Test]
    public function failsWhenRequiredFieldIsBlank(): void
    {
        $form = new FixtureFormRequest($this->request(['title' => '']), ['title' => new Assert\NotBlank()]);

        self::assertArrayHasKey('title', $this->validationErrors($form));
    }

    #[Test]
    public function rejectsAnEmptyArrayForNotBlank(): void
    {
        $form = new FixtureFormRequest($this->request(['tags' => []]), ['tags' => new Assert\NotBlank()]);

        self::assertArrayHasKey('tags', $this->validationErrors($form));
    }

    #[Test]
    public function passesAndExposesValidatedData(): void
    {
        $form = new FixtureFormRequest(
            $this->request(['title' => 'Hello', 'tags' => ['a']]),
            ['title' => [new Assert\NotBlank(), new Assert\Length(max: 255)], 'tags' => new Assert\Count(min: 1)],
        );

        $form->validate();

        self::assertSame(['title' => 'Hello', 'tags' => ['a']], $form->validated());
    }

    #[Test]
    public function acceptsStringZeroAsPresent(): void
    {
        // '0' is falsy but a valid present value: NotBlank must not reject it.
        $form = new FixtureFormRequest($this->request(['flag' => '0']), ['flag' => new Assert\NotBlank()]);

        $form->validate();

        self::assertSame('0', $form->validated()['flag']);
    }

    #[Test]
    public function reportsAMissingRequiredField(): void
    {
        // Not wrapped in Assert\Optional, so the field is required to be present.
        $form = new FixtureFormRequest($this->request([]), ['email' => new Assert\Email()]);

        self::assertArrayHasKey('email', $this->validationErrors($form));
    }

    #[Test]
    public function optionalFieldMayBeAbsent(): void
    {
        $form = new FixtureFormRequest($this->request([]), ['nickname' => new Assert\Optional(new Assert\Length(max: 10))]);

        $form->validate();

        self::assertSame([], $form->validated());
    }

    #[Test]
    public function blankOptionalFieldIsCoercedToNullAndPasses(): void
    {
        // HTML submits an untouched optional field as '' (present, not absent), so
        // Assert\Optional alone would not skip it and Type('numeric') would reject
        // the blank — the /tickets/new agent_id bug. Blank input is coerced to null,
        // which Symfony's Type/Choice/... treat as valid.
        $form = new FixtureFormRequest(
            $this->request(['agent_id' => '']),
            ['agent_id' => new Assert\Optional(new Assert\Type('numeric'))],
        );

        $form->validate();

        self::assertNull($form->validated()['agent_id']);
    }

    #[Test]
    public function onlyDeclaredFieldsReachValidatedData(): void
    {
        $form = new FixtureFormRequest(
            $this->request(['role' => 'admin', 'is_admin' => '1']),
            ['role' => new Assert\Choice(choices: ['admin', 'user'])],
        );

        $form->validate();

        // 'is_admin' was never declared, so mass assignment is impossible.
        self::assertSame(['role' => 'admin'], $form->validated());
    }

    #[Test]
    public function collectsViolationsFromMultipleFields(): void
    {
        $form = new FixtureFormRequest(
            $this->request(['title' => '', 'email' => 'nope']),
            ['title' => new Assert\NotBlank(), 'email' => new Assert\Email()],
        );

        $errors = $this->validationErrors($form);

        self::assertArrayHasKey('title', $errors);
        self::assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function rejectsAMalformedJsonBodyAsBadRequest(): void
    {
        $form = new FixtureFormRequest($this->jsonRequest('{"title": '), ['title' => new Assert\NotBlank()]);

        try {
            $form->validate();
            self::fail('Expected MiddagDomainException');
        } catch (MiddagDomainException $middagDomainException) {
            self::assertSame(400, $middagDomainException->getStatusCode());
        }
    }

    #[Test]
    public function parsesAValidJsonBody(): void
    {
        $form = new FixtureFormRequest(
            $this->jsonRequest('{"title": "Hello"}'),
            ['title' => new Assert\NotBlank()],
        );

        $form->validate();

        self::assertSame(['title' => 'Hello'], $form->validated());
    }

    #[Test]
    public function anEmptyJsonBodyFallsBackToTheQueryString(): void
    {
        // An empty body is not "malformed": it must not raise a 400.
        $form = new FixtureFormRequest($this->jsonRequest(''), ['nickname' => new Assert\Optional()]);

        $form->validate();

        self::assertSame([], $form->validated());
    }

    #[Test]
    public function routesValidationMessagesThroughTheTranslator(): void
    {
        $form = new FixtureFormRequest($this->request(['title' => '']), ['title' => new Assert\NotBlank()]);
        $form->setTranslator(new class implements TranslatorInterface {
            public function get(string $key, string $component = '', array $params = []): string
            {
                return 'TRANSLATED:' . $component;
            }

            public function has(string $key, string $component = ''): bool
            {
                return true;
            }
        });

        self::assertSame('TRANSLATED:validators', $this->validationErrors($form)['title']);
    }

    /**
     * @return array<string, string|string[]>
     */
    private function validationErrors(FixtureFormRequest $form): array
    {
        try {
            $form->validate();
            self::fail('Expected MiddagValidationException');
        } catch (MiddagValidationException $middagValidationException) {
            return $middagValidationException->errors();
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return Request::create('/x', 'POST', $body);
    }

    private function jsonRequest(string $content): Request
    {
        return Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $content);
    }
}

final class FixtureFormRequest extends AbstractFormRequest
{
    /**
     * @param array<string, array<Constraint>|Constraint> $ruleset
     */
    public function __construct(Request $request, private readonly array $ruleset)
    {
        parent::__construct($request);
    }

    public function rules(): array
    {
        return $this->ruleset;
    }
}
