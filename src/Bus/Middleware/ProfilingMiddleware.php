<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Middleware;

use Middag\Framework\Observability\Contract\ProfileCollectorInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Symfony Messenger middleware that records every dispatched message.
 *
 * Drop it into the bus middleware stack ({@see MessageBusFactory}) to give the
 * profiler a `bus` timeline — message class plus handling duration — without
 * decorating the bus or the handlers. Records even when a handler throws (the
 * timing is captured in a finally), so failures show up in the profile too.
 *
 * @api
 */
final readonly class ProfilingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProfileCollectorInterface $collector,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $start = hrtime(true);

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->collector->record('bus', $message::class, [], (hrtime(true) - $start) / 1_000_000);
        }
    }
}
