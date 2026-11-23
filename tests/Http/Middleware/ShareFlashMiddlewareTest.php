<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Middleware;

use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Http\Middleware\ShareFlashMiddleware;
use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Ui\Form\FormErrors;
use Middag\Ui\Shared\ValueObject\FlashMessages;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The middleware surfaces (and consumes) the flash store as the Inertia
 * `flash` / `errors` shared props before delegating.
 *
 * @internal
 */
#[CoversClass(ShareFlashMiddleware::class)]
final class ShareFlashMiddlewareTest extends TestCase
{
    #[Test]
    public function sharesFlashAndErrorsThenClearsTheBag(): void
    {
        $flash = new FlashBag($session = new ArraySession());
        $flash->add('success', 'Saved.');
        $flash->setErrors(['title' => 'Required']);

        (new ShareFlashMiddleware($flash))->process(new ServerRequest('GET', '/'), $this->passthrough());

        $shared = InertiaManager::getShared();
        $this->assertInstanceOf(FlashMessages::class, $shared['flash']);
        $this->assertInstanceOf(FormErrors::class, $shared['errors']);
        $this->assertSame('{"success":"Saved."}', json_encode($shared['flash'], JSON_THROW_ON_ERROR));
        $this->assertSame('{"title":"Required"}', json_encode($shared['errors'], JSON_THROW_ON_ERROR));

        // One-shot: the bag is emptied by the pull.
        $this->assertFalse($session->has('_middag_flash'));
        $this->assertFalse($session->has('_middag_errors'));
    }

    #[Test]
    public function discardsForeignFlashKeysAndNonStringValues(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->add('success', 'Saved.');
        $flash->add('toast', 'nope');
        $flash->add('error', ['not' => 'a string']);

        (new ShareFlashMiddleware($flash))->process(new ServerRequest('GET', '/'), $this->passthrough());

        $this->assertSame('{"success":"Saved."}', json_encode(InertiaManager::getShared()['flash'], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function emptyBucketsSerializeAsEmptyObjects(): void
    {
        (new ShareFlashMiddleware(new FlashBag(new ArraySession())))->process(new ServerRequest('GET', '/'), $this->passthrough());

        $shared = InertiaManager::getShared();
        $this->assertSame('{}', json_encode($shared['flash'], JSON_THROW_ON_ERROR));
        $this->assertSame('{}', json_encode($shared['errors'], JSON_THROW_ON_ERROR));
    }

    private function passthrough(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Psr17Factory())->createResponse(204);
            }
        };
    }
}
