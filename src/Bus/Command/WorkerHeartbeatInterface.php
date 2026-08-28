<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Command;

/**
 * Liveness seam for a long-running {@see CommandWorker}.
 *
 * `drain()` calls {@see self::beat()} once per loop cycle so an external
 * supervisor (a systemd watchdog, a "last seen" row a health check reads, a
 * process manager) can tell a hung worker from an idle one. This package
 * ships no implementation: recording a heartbeat always means touching a host
 * resource (a DB row, a file, a cache key) that does not belong in an OSS
 * pipe. Bind this interface in the host application when liveness monitoring
 * is needed; leave it unbound and the worker simply does not beat.
 *
 * @api
 */
interface WorkerHeartbeatInterface
{
    /**
     * Record that $workerName is alive and currently draining $transportNames.
     *
     * Must be cheap and must never throw: a beat failure is a monitoring
     * concern, not a reason to interrupt message processing.
     *
     * @param list<string> $transportNames transport names this worker drains
     */
    public function beat(string $workerName, array $transportNames): void;
}
