<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Attribute;

use Attribute;
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;

/**
 * Declares authentication and authorization requirements for a controller action.
 *
 * Applied to methods (per-action) or classes (default for all actions).
 * Method-level takes precedence over class-level.
 *
 * Platform adapters should prefer `requirements` for rich authorization data.
 * The legacy `capabilities`, `context`, and `instanceId` fields remain for
 * consumers that still pass bare permission strings.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Auth
{
    /**
     * @var list<string>
     */
    public array $capabilities;

    /**
     * @var list<CapabilityRequirement>
     */
    public array $requirements;

    /**
     * @param bool                                                                                                                             $login        Whether authentication is required
     * @param list<CapabilityDefinitionInterface|CapabilityReference|CapabilityRequirement|string>                                             $capabilities Legacy capability list; strings remain supported for BC
     * @param string                                                                                                                           $context      Legacy context type for string capability checks
     * @param int                                                                                                                              $instanceId   Legacy instance ID for non-global context scopes
     * @param list<CapabilityDefinitionInterface|CapabilityReference|CapabilityRequirement|class-string<CapabilityDefinitionInterface>|string> $requirements Rich requirements adapters can resolve without string-only loss
     */
    public function __construct(
        public bool $login = true,
        array $capabilities = [],
        public string $context = 'system',
        public int $instanceId = 0,
        array $requirements = [],
    ) {
        $this->requirements = CapabilityRequirement::listFrom([...$capabilities, ...$requirements]);
        $this->capabilities = array_values(array_filter(
            array_map(
                static fn (CapabilityRequirement $requirement): ?string => $requirement->legacyCapability(),
                $this->requirements,
            ),
            static fn (?string $capability): bool => $capability !== null,
        ));
    }
}
