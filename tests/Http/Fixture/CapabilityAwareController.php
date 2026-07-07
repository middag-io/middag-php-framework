<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture;

use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityRequirementAwareInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller fixture that opts in to rich #[Auth] requirements.
 *
 * Records the CapabilityRequirement list the kernel forwards and echoes it in
 * the response body so the propagation can be asserted end-to-end.
 *
 * @internal
 */
final class CapabilityAwareController extends AbstractController implements CapabilityRequirementAwareInterface
{
    /** @var list<CapabilityRequirement> */
    public array $captured = [];

    public function setRequireCapabilityRequirements(array $requirements): void
    {
        $this->captured = $requirements;
    }

    #[Auth(requirements: ['moodle/course:view', 'mod/quiz:attempt'])]
    public function guarded(): JsonResponse
    {
        return new JsonResponse([
            'keys' => array_map(
                static fn (CapabilityRequirement $requirement): ?string => $requirement->key(),
                $this->captured,
            ),
        ]);
    }
}
