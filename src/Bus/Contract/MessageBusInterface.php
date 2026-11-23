<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Contract;

use Symfony\Component\Messenger\MessageBusInterface as SymfonyMessageBusInterface;

/**
 * The single MIDDAG dispatch surface — a MIDDAG-scoped specialization of Symfony
 * Messenger's MessageBusInterface (`dispatch($message, $stamps): Envelope`).
 *
 * One bus for sync and async: callers always dispatch a message and the
 * configured middleware stack decides where it runs (handled inline by default;
 * sent to a transport when the message type is routed). Depending on this
 * framework-scoped contract keeps callers off symfony/messenger directly.
 *
 * Note: this contract and {@see TransportInterface} are the *only* pair
 * allowed to `extends` Symfony Messenger directly, because async dispatch
 * routes through Symfony Messenger. Every other bridge contract
 * (e.g. {@see SignalDispatcherInterface}, the Translator) stays a pure MIDDAG
 * contract with no host/library coupling.
 *
 * @api
 *
 * @see MessageBus        The shipped implementation (extends Symfony's MessageBus).
 * @see MessageBusFactory Builds the default bus with the MIDDAG middleware stack.
 */
interface MessageBusInterface extends SymfonyMessageBusInterface {}
