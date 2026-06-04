<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

/**
 * Marker interface for the Query Builder.
 *
 * Lets callers type-hint the builder without depending on the concrete
 * implementation. Implementations are immutable: every fluent method returns a
 * new builder instance and never mutates the receiver, so a partially-configured
 * builder can be shared and reused without side effects.
 *
 * @api
 */
interface QueryBuilderInterface {}
