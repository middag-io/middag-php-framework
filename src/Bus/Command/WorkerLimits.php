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

use InvalidArgumentException;

/**
 * Immutable stop conditions for {@see CommandWorker::drain()}: how many
 * messages to process, how long to run, and how much memory to tolerate
 * before shutting down cleanly. Every field is nullable — null means
 * unlimited on that axis.
 *
 * Parsing the human-friendly CLI forms (`--limit=100`, `--time-limit=3600`,
 * `--memory-limit=256M`) belongs here, via {@see self::fromCliOptions()}, not
 * in the worker: the worker only ever compares plain numbers.
 *
 * @api
 */
final readonly class WorkerLimits
{
    public function __construct(
        public ?int $messageLimit = null,
        public ?int $timeLimitSeconds = null,
        public ?int $memoryLimitBytes = null,
    ) {
        if ($messageLimit !== null && $messageLimit < 0) {
            throw new InvalidArgumentException('messageLimit must not be negative.');
        }
        if ($timeLimitSeconds !== null && $timeLimitSeconds < 0) {
            throw new InvalidArgumentException('timeLimitSeconds must not be negative.');
        }
        if ($memoryLimitBytes !== null && $memoryLimitBytes < 0) {
            throw new InvalidArgumentException('memoryLimitBytes must not be negative.');
        }
    }

    /**
     * No stop condition on any axis — drain() runs until its transports are empty.
     */
    public static function unlimited(): self
    {
        return new self();
    }

    /**
     * Named constructor accepting the human-friendly forms a CLI layer would
     * carry verbatim from `--limit`, `--time-limit`, and `--memory-limit`
     * flags.
     *
     * $memoryLimit accepts php.ini's `memory_limit` grammar: a plain integer
     * byte count, or an integer followed by a `K`/`M`/`G` suffix
     * (case-insensitive) for kibi/mebi/gibibytes.
     */
    public static function fromCliOptions(
        ?int $messageLimit = null,
        ?int $timeLimitSeconds = null,
        ?string $memoryLimit = null,
    ): self {
        return new self($messageLimit, $timeLimitSeconds, self::parseMemoryLimit($memoryLimit));
    }

    /**
     * Has $handled already reached the message limit (if any)?
     */
    public function messageLimitReached(int $handled): bool
    {
        return $this->messageLimit !== null && $handled >= $this->messageLimit;
    }

    /**
     * Has $elapsedSeconds already reached the time limit (if any)?
     */
    public function timeLimitReached(float $elapsedSeconds): bool
    {
        return $this->timeLimitSeconds !== null && $elapsedSeconds >= $this->timeLimitSeconds;
    }

    /**
     * Has $currentUsageBytes already reached the memory limit (if any)?
     */
    public function memoryLimitReached(int $currentUsageBytes): bool
    {
        return $this->memoryLimitBytes !== null && $currentUsageBytes >= $this->memoryLimitBytes;
    }

    private static function parseMemoryLimit(?string $memoryLimit): ?int
    {
        if ($memoryLimit === null || trim($memoryLimit) === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*([kKmMgG]?)$/', trim($memoryLimit), $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid memory limit "%s".', $memoryLimit));
        }

        $bytes = (int) $matches[1];

        return match (strtolower($matches[2])) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }
}
