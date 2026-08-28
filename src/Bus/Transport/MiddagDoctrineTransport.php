<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Transport;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\TransportInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;

/**
 * Symfony's Doctrine transport, satisfying the MIDDAG-scoped
 * {@see TransportInterface} (core#164 F6 6.1).
 *
 * Interface satisfaction in PHP is nominal, not structural: the bridge
 * implements Symfony's `TransportInterface` only, while every MIDDAG seam
 * ({@see TransportLocator}, {@see CommandWorker})
 * asks for the MIDDAG one — which is a pure alias of Symfony's, adding no
 * method. Subclassing with an EMPTY body is therefore the whole fix, and it is
 * deliberately preferred over a delegating wrapper: the bridge also implements
 * `SetupableTransportInterface`, `MessageCountAwareInterface`,
 * `ListableReceiverInterface` and `KeepaliveReceiverInterface`, and a wrapper
 * that forwarded only `send/get/ack/reject` would silently drop all four —
 * taking `setup()` (the `auto_setup` path) and the message count a status
 * screen reads with it.
 *
 * @api
 */
final class MiddagDoctrineTransport extends DoctrineTransport implements TransportInterface {}
