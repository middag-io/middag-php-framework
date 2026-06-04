<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

/**
 * A first-load-skipping prop that the client fetches automatically after mount.
 *
 * Extends {@see IgnoreFirstLoadInterface} (so it is skipped on the initial load
 * exactly like `optional()`), but additionally announces itself: on the initial
 * response {@see InertiaResponse::resolveProps()} records its key under
 * {@see self::group()} in the page object's `deferredProps`, so the Inertia
 * client knows to issue a follow-up partial reload per group. Resolution then
 * happens on that partial, through the same lazy path as optional props.
 *
 * @internal
 */
interface DeferrableInterface extends IgnoreFirstLoadInterface
{
    /**
     * The reload group this prop belongs to.
     *
     * Props sharing a group are fetched together in one partial reload.
     */
    public function group(): string;

    /**
     * Whether a resolution failure should drop the prop instead of failing the
     * request (the prop's key is reported under the page object's `rescuedProps`).
     */
    public function rescue(): bool;
}
