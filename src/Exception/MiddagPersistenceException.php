<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Exception;

/**
 * Database or repository persistence failure.
 *
 * Specialization of infrastructure exception for storage-layer errors.
 * Wraps the host's native database exceptions (a PDOException, or a host
 * adapter's DML exception) at the boundary layer.
 *
 * @api
 */
class MiddagPersistenceException extends MiddagInfrastructureException {}
