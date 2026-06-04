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
 * Contract that all Facade classes must implement.
 *
 * This interface exposes *only* the minimal public API
 * required by consumers. All resolution and container logic
 * remains internal to the `AbstractFacade` base class.
 *
 * @api
 */
interface FacadeInterface
{
    /**
     * Return the identifier/class name of the service
     * that the facade resolves from the Kernel container.
     *
     * Example:
     * return MyServiceInterface::class;
     *
     * @return string the service ID or class name
     */
    public static function getFacadeAccessor(): string;
}
