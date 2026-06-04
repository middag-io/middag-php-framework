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

use Symfony\Component\Messenger\Transport\TransportInterface as SymfonyTransportInterface;

/**
 * Generic MIDDAG transport contract.
 *
 * Adapters implement this to plug their native task/queue system into the
 * Symfony Messenger transport pipeline used by MessageBusInterface.
 *
 * Inherits Symfony's TransportInterface (send/get/ack/reject) verbatim;
 * exists as a MIDDAG-scoped alias so callers depend on the framework, not on
 * symfony/messenger directly.
 *
 * @api
 */
interface TransportInterface extends SymfonyTransportInterface {}
