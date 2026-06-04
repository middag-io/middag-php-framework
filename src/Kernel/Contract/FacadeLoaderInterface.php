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
 * Contract for facade discovery loaders.
 *
 * Implementations scan namespaces to register facade definitions discovered
 * during kernel bootstrap.
 *
 * @internal
 */
interface FacadeLoaderInterface extends LoaderInterface {}
