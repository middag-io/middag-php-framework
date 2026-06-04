<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Command;

use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Contract\TransportInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Throwable;

/**
 * Drains queued messages from a transport and re-dispatches each through the
 * {@see MessageBus} carrying a {@see ReceivedStamp} — so the send middleware
 * skips re-queueing and the handle middleware runs the handler. Async execution
 * therefore reuses the exact same bus and handler resolution as sync, no
 * duplication.
 *
 * Call drain() from a long-lived process (systemd/supervisor) or a periodic
 * OS/host cron invocation; symfony/console (suggested) wraps it as a
 * `messenger:consume`-style CLI worker.
 *
 * @api
 */
final readonly class CommandWorker
{
    public function __construct(
        private TransportInterface $transport,
        private MessageBusInterface $bus,
        private string $transportName = 'async',
    ) {}

    /**
     * Process every message currently queued on the transport.
     *
     * @return int number of messages handled
     */
    public function drain(): int
    {
        $handled = 0;

        foreach ($this->transport->get() as $envelope) {
            try {
                $this->bus->dispatch($envelope->with(new ReceivedStamp($this->transportName)));
            } catch (Throwable $throwable) {
                $this->transport->reject($envelope);

                throw $throwable;
            }

            $this->transport->ack($envelope);
            ++$handled;
        }

        return $handled;
    }
}
