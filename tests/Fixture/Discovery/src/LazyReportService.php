<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Fixture\Discovery\src;

use Middag\Framework\Kernel\Attribute\Lazy;

/**
 * Discovery fixture: a registrable `Service` marked #[Lazy], so its container
 * definition is flagged lazy by scanDirectory().
 *
 * @internal
 */
#[Lazy]
class LazyReportService
{
    public function run(): string
    {
        return 'report';
    }
}
