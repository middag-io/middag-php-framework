<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Fixture;

use Middag\Framework\Form\Contract\FormValidatorInterface;

/**
 * A validator a host binds before discovery runs, in place of the default.
 *
 * @internal
 */
final class HostFormValidator implements FormValidatorInterface
{
    public function validate(array $schema, array $values): array
    {
        return [];
    }
}
