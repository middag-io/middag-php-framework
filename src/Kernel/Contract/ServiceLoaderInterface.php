<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Contract;

/**
 * Contract for auto-discovery service loaders.
 *
 * Implementations locate service classes and register them with the container
 * including autowiring metadata.
 *
 * @api
 */
interface ServiceLoaderInterface extends LoaderInterface {}
