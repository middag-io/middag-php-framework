<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\ConventionHandlersLocator;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Symfony\Component\Messenger\MessageBus as SymfonyMessageBus;

/**
 * The single MIDDAG dispatch surface — a Symfony Messenger {@see SymfonyMessageBus}
 * that also satisfies the framework-scoped {@see MessageBusInterface}.
 *
 * One bus for both sync and async: callers always {@see self::dispatch()} a
 * message; the configured middleware stack decides where it runs. With no sender
 * routing the message is handled inline (the batteries-included default); routing
 * a message type to a transport makes it async, drained later by {@see CommandWorker}.
 *
 * Assemble it through {@see MessageBusFactory}, which wires the MIDDAG middleware
 * stack (send + handle) and the {@see ConventionHandlersLocator}.
 *
 * @internal
 *
 * @see MessageBusInterface The framework-scoped contract (extends Symfony's).
 * @see MessageBusFactory   Builds this bus with the default middleware stack.
 */
final class MessageBus extends SymfonyMessageBus implements MessageBusInterface {}
