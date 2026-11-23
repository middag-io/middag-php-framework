<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Middleware;

use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Ui\Form\FormErrorBags;
use Middag\Ui\Form\FormErrors;
use Middag\Ui\Shared\ValueObject\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Surfaces the one-shot flash store as Inertia shared props.
 *
 * At the front of the pipeline it pulls (and clears) the {@see FlashBag} buckets
 * and shares them as the `flash` and `errors` props, so every Inertia response
 * this request produces carries the messages and validation errors left by the
 * previous request's redirect — the read side of post-redirect-get. Pairs with
 * the kernel, which flashes validation errors before a redirect-back (web
 * half), and with controllers calling `flash()` / `redirectBack()`.
 *
 * The buckets reach the props as ui value objects: `flash` goes through
 * {@see FlashMessages::fromArray()} (keys outside success/error/info/warning
 * and non-string values are discarded), and `errors` is what
 * {@see FlashBag::pullErrors()} returns: a {@see FormErrors}, or a
 * {@see FormErrorBags} when the failed request named an error bag
 * (`X-Inertia-Error-Bag`), in which case the client that sent the header
 * reads `errors[bag]`.
 *
 * @api
 */
final readonly class ShareFlashMiddleware implements MiddlewareInterface
{
    public function __construct(
        private FlashBag $flash,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        InertiaManager::share('flash', FlashMessages::fromArray($this->flash->pull()));
        InertiaManager::share('errors', $this->flash->pullErrors());

        return $handler->handle($request);
    }
}
