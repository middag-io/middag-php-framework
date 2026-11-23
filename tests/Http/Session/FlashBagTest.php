<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Session;

use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Ui\Form\FieldMessage;
use Middag\Ui\Form\FormErrorBags;
use Middag\Ui\Form\FormErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * One-shot flash semantics: queued messages and errors are read exactly
 * once, then cleared.
 *
 * @internal
 */
#[CoversClass(FlashBag::class)]
final class FlashBagTest extends TestCase
{
    #[Test]
    public function pullReturnsQueuedMessagesThenClearsThem(): void
    {
        $flash = new FlashBag($session = new ArraySession());
        $flash->add('success', 'Saved.');
        $flash->add('info', 'FYI');

        $this->assertSame(['success' => 'Saved.', 'info' => 'FYI'], $flash->pull());
        $this->assertSame([], $flash->pull(), 'flash is one-shot');
        $this->assertFalse($session->has('_middag_flash'));
    }

    #[Test]
    public function pullErrorsReturnsValidationMapThenClearsIt(): void
    {
        $flash = new FlashBag($session = new ArraySession());
        $flash->setErrors(new FormErrors(['email' => 'Invalid email', 'password' => ['Too short', 'No digit']]));

        $errors = $flash->pullErrors();

        $this->assertInstanceOf(FormErrors::class, $errors);
        $this->assertSame(
            '{"email":"Invalid email","password":["Too short","No digit"]}',
            json_encode($errors, JSON_THROW_ON_ERROR),
        );
        $this->assertFalse($session->has('_middag_errors'));
        $this->assertSame('{}', json_encode($flash->pullErrors(), JSON_THROW_ON_ERROR), 'errors are one-shot');
    }

    #[Test]
    public function storesOnePlainArrayEnvelopeInTheSession(): void
    {
        $flash = new FlashBag($session = new ArraySession());
        $flash->setErrors(new FormErrors([
            'email' => new FieldMessage('Invalid email', 'validation.email', 'validators'),
        ]), 'login');

        $this->assertSame(
            [
                'bag' => 'login',
                'errors' => ['email' => ['message' => 'Invalid email', 'key' => 'validation.email', 'domain' => 'validators']],
            ],
            $session->get('_middag_errors'),
        );
    }

    #[Test]
    public function structuredMessagesRoundTripThroughTheSession(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->setErrors(new FormErrors([
            'email' => new FieldMessage('Invalid email', 'validation.email', 'validators'),
            'tags' => ['Too many', new FieldMessage('Not unique', 'validation.unique', 'validators', ['limit' => 3])],
        ]));

        $this->assertSame(
            '{"email":{"message":"Invalid email","key":"validation.email","domain":"validators"},'
            . '"tags":["Too many",{"message":"Not unique","key":"validation.unique","domain":"validators","params":{"limit":3}}]}',
            json_encode($flash->pullErrors(), JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function errorsQueuedUnderABagReadBackAsFormErrorBags(): void
    {
        $flash = new FlashBag($session = new ArraySession());
        $flash->setErrors(new FormErrors(['title' => 'Required']), 'createTask');

        $errors = $flash->pullErrors();

        $this->assertInstanceOf(FormErrorBags::class, $errors);
        $this->assertSame('{"createTask":{"title":"Required"}}', json_encode($errors, JSON_THROW_ON_ERROR));
        $this->assertFalse($session->has('_middag_errors'), 'the bag is cleared with its errors');
        $this->assertInstanceOf(FormErrors::class, $flash->pullErrors(), 'no bag name survives the pull');
    }

    #[Test]
    public function aLaterWriteReplacesTheBagName(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->setErrors(new FormErrors(['title' => 'Required']), 'createTask');
        $flash->setErrors(new FormErrors(['name' => 'Required']));

        $errors = $flash->pullErrors();

        $this->assertInstanceOf(FormErrors::class, $errors);
        $this->assertSame('{"name":"Required"}', json_encode($errors, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function anEmptyBagNameMeansNoBag(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->setErrors(new FormErrors(['title' => 'Required']), '');

        $this->assertInstanceOf(FormErrors::class, $flash->pullErrors());
    }

    #[Test]
    public function numericBagAndFieldNamesStayJsonObjects(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->setErrors(new FormErrors(['0' => 'First', '1' => 'Second']), '0');

        $this->assertSame('{"0":{"0":"First","1":"Second"}}', json_encode($flash->pullErrors(), JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function anEmptyMapRoundTripsAsAnEmptyObject(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->setErrors(new FormErrors());

        $errors = $flash->pullErrors();

        $this->assertInstanceOf(FormErrors::class, $errors);
        $this->assertSame('{}', json_encode($errors, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function aSessionValueThatIsNotAnEnvelopeReadsAsNoErrors(): void
    {
        // e.g. a flat map flashed by an older release just before a deploy.
        $session = new ArraySession();
        $session->set('_middag_errors', ['title' => 'Required']);

        $flash = new FlashBag($session);

        $errors = $flash->pullErrors();

        $this->assertInstanceOf(FormErrors::class, $errors);
        $this->assertSame('{}', json_encode($errors, JSON_THROW_ON_ERROR));
        $this->assertFalse($session->has('_middag_errors'));
    }

    #[Test]
    public function flashAndErrorsAreIndependentBuckets(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->add('success', 'Saved.');
        $flash->setErrors(new FormErrors(['name' => 'Required']));

        $this->assertSame('{"name":"Required"}', json_encode($flash->pullErrors(), JSON_THROW_ON_ERROR));
        $this->assertSame(['success' => 'Saved.'], $flash->pull(), 'pulling errors must not clear flash');
    }

    #[Test]
    public function emptyByDefault(): void
    {
        $flash = new FlashBag(new ArraySession());

        $this->assertSame([], $flash->pull());
        $errors = $flash->pullErrors();
        $this->assertInstanceOf(FormErrors::class, $errors);
        $this->assertSame('{}', json_encode($errors, JSON_THROW_ON_ERROR));
    }
}
