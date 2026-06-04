<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel;

use Middag\Framework\Http\FatalErrorHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Runs registered cleanup callbacks on PHP shutdown — including after a fatal
 * error — so framework-owned resources are released deterministically instead
 * of waiting on the garbage collector.
 *
 * Scope is **framework-owned resources only**: MIDDAG-opened DB connections,
 * MIDDAG locks, temp buffers/handles the framework created. It deliberately does
 * NOT touch host resources ($DB/$wpdb connections, host sessions, host file
 * handles) — those are owned and torn down by the host's own shutdown path.
 * Cleaning host state from here would race the host and is out of bounds.
 *
 * Each callback runs isolated: a throwing callback is caught and logged, then
 * the remaining callbacks still run (one bad teardown never blocks the rest).
 * Callbacks run in reverse registration order (LIFO), mirroring resource
 * acquisition nesting.
 *
 * Registration is a global side-effect (opt-in): the host adapter / front
 * controller calls {@see self::register()} once, early. Pairs with
 * {@see FatalErrorHandler} — that one *reports* the fatal,
 * this one *releases resources* on the way down.
 *
 * @api
 */
final class ShutdownCleanup
{
    /** @var list<array{label: string, callback: callable}> */
    private array $tasks = [];

    private bool $registered = false;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Register the shutdown hook once. Idempotent: extra calls are no-ops.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;
        register_shutdown_function($this->run(...));
    }

    /**
     * Queue a framework-owned cleanup callback. $label is used only in the log
     * message when the callback throws.
     */
    public function addCleanup(callable $callback, string $label = 'cleanup'): void
    {
        $this->tasks[] = ['label' => $label, 'callback' => $callback];
    }

    /**
     * Run all queued cleanups in LIFO order, isolating failures. Safe to call
     * directly in tests; also the registered shutdown callback.
     */
    public function run(): void
    {
        foreach (array_reverse($this->tasks) as $task) {
            try {
                ($task['callback'])();
            } catch (Throwable $e) {
                $this->logger->error('[shutdown] Cleanup "{label}" failed: {message}', [
                    'label' => $task['label'],
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        // A cleanup must not run twice if run() is invoked again.
        $this->tasks = [];
    }
}
