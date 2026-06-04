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

/**
 * Declares authentication and authorization requirements for a controller action.
 *
 * Applied to methods (per-action) or classes (default for all actions).
 * Method-level takes precedence over class-level.
 *
 * Platform adapters interpret the `capabilities`, `context`, and `instanceId`
 * fields using their native authorization systems. Platform-specific concerns
 * (e.g. Moodle sesskey, WordPress nonce) belong in companion attributes
 * defined by each adapter.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Auth
{
    /**
     * @param bool     $login        Whether authentication is required
     * @param string[] $capabilities Required capability/permission names (adapter interprets)
     * @param string   $context      Context type for capability checks (adapter interprets)
     * @param int      $instanceId   Instance ID for non-global context scopes
     */
    public function __construct(
        public bool $login = true,
        public array $capabilities = [],
        public string $context = 'system',
        public int $instanceId = 0,
    ) {}
}
