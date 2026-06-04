<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Hour-bucket file handler with 5MB overflow rotation.
 *
 * Directory layout:
 *
 *   {base}/{module}/{channel}/{Y-m-d-H-00-00}.log
 *
 * When the current bucket file reaches 5MB it spills over to a
 * second-granular file `{Y-m-d-H-i-s}.log` in the same directory.
 *
 * @api
 */
final class RotatingStreamHandler extends AbstractProcessingHandler
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    public function __construct(
        private readonly string $basePath,
        private readonly string $module,
        private readonly string $channel,
        int|Level|string $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $dir = $this->basePath . '/' . $this->module . '/' . $this->channel;

        // mkdir can race with a concurrent writer (TOCTOU): re-stat after a
        // failed attempt and only escalate when the directory is truly absent.
        if (!is_dir($dir) && !$this->silenced(static fn (): bool => mkdir($dir, 0755, true)) && !is_dir($dir)) {
            $this->escalate('cannot create log directory ' . $dir, $record);

            return;
        }

        $filepath = $dir . '/' . date('Y-m-d-H-00-00') . '.log';

        if ($this->isFull($filepath)) {
            // The hour bucket overflowed: spill to a second-granular file,
            // rolling a monotonic suffix so repeated spills within the same
            // second do not grow one file without bound.
            $filepath = $this->spillTarget($dir);
        }

        $written = $this->silenced(
            static fn (): false|int => file_put_contents($filepath, (string) $record->formatted, FILE_APPEND | LOCK_EX),
        );

        if ($written === false) {
            $this->escalate('cannot write to ' . $filepath, $record);
        }
    }

    /**
     * Escalate a filesystem failure to PHP's error_log instead of throwing.
     *
     * A logging handler must not throw (PSR-3): callers — including defensive
     * `catch (Throwable) { ...log... }` sites in the loaders — would otherwise
     * turn a swallowed, non-fatal error into a crash. error_log keeps the
     * failure AND the would-be-dropped line visible, so nothing is lost silently
     * and no caller breaks.
     */
    private function escalate(string $reason, LogRecord $record): void
    {
        error_log(sprintf('RotatingStreamHandler: %s; dropped log line: %s', $reason, trim((string) $record->formatted)));
    }

    /**
     * Pick a spill file under the size cap, appending a monotonic numeric
     * suffix when the second-granular base (or an earlier suffix) is already
     * full — so an overflowing spill rolls to a fresh file instead of growing
     * unbounded within the same second.
     */
    private function spillTarget(string $dir): string
    {
        $base = $dir . '/' . date('Y-m-d-H-i-s');
        $candidate = $base . '.log';

        for ($suffix = 1; $this->isFull($candidate); ++$suffix) {
            $candidate = sprintf('%s-%d.log', $base, $suffix);
        }

        return $candidate;
    }

    /**
     * Whether the file exists and has reached the rotation cap. Re-stats first
     * so a stale size from PHP's stat cache cannot defeat the check.
     */
    private function isFull(string $filepath): bool
    {
        clearstatcache(true, $filepath);

        return is_file($filepath) && filesize($filepath) >= self::MAX_FILE_SIZE;
    }

    /**
     * Run an I/O call with PHP warnings swallowed, so a filesystem failure
     * surfaces through our own return-value/exception path instead of emitting
     * a warning (which the suite treats as a failure).
     *
     * @template T
     *
     * @param callable(): T $io
     *
     * @return T
     */
    private function silenced(callable $io): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $io();
        } finally {
            restore_error_handler();
        }
    }
}
