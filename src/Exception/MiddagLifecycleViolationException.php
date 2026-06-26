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

use Middag\Framework\Kernel\ContainerFactory;

/**
 * Container lifecycle phase violated.
 *
 * Thrown by the kernel when code attempts a register-phase operation outside
 * its allowed phase — notably {@see ContainerFactory::addSynthetic()}
 * called after {@see ContainerFactory::build()} has
 * compiled the container. This is a developer error, not a runtime failure.
 *
 * @api
 */
class MiddagLifecycleViolationException extends MiddagException {}
