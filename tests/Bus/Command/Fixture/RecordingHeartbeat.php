<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Command\Fixture;

use Middag\Framework\Bus\Command\WorkerHeartbeatInterface;

/**
 * {@see WorkerHeartbeatInterface} spy: records every beat for assertions.
 *
 * @internal
 */
final class RecordingHeartbeat implements WorkerHeartbeatInterface
{
    /** @var list<array{workerName: string, lanes: list<string>}> */
    public array $beats = [];

    public function beat(string $workerName, array $lanes): void
    {
        $this->beats[] = ['workerName' => $workerName, 'lanes' => $lanes];
    }
}
