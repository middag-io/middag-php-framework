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

use Middag\Framework\Http\Auth\CapabilityRequirement;

/**
 * Opt-in capability for controllers that consume rich #[Auth] requirements.
 *
 * The universal {@see ControllerInterface} carries only the legacy string
 * surface (`setRequireCapabilities()`): a flat capability list plus one
 * class-wide context/instanceId, applied uniformly to every capability. That
 * loses the per-requirement data an #[Auth] attribute can hold — each
 * {@see CapabilityRequirement} can carry its own context (via `options`,
 * e.g. `contextlevel`), its own {@see CapabilityReference::$host} discriminator,
 * and a {@see CapabilityDefinitionInterface} definition.
 *
 * Adapters whose controllers can honour per-requirement authorization
 * (e.g. the Moodle adapter resolving a distinct context level per capability)
 * implement this to receive the rich list. The kernel calls it in addition to
 * the legacy `setRequireCapabilities()`, so adapters that do not implement it
 * keep working unchanged — this is why it is a separate opt-in contract rather
 * than a method on the universal ControllerInterface.
 *
 * @api
 */
interface CapabilityRequirementAwareInterface
{
    /**
     * Declare the rich capability requirements derived from #[Auth].
     *
     * @param list<CapabilityRequirement> $requirements
     */
    public function setRequireCapabilityRequirements(array $requirements): void;
}
