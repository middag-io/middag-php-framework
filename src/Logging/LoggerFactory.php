<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging;

use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\Contract\SecretRedactorInterface;
use Middag\Framework\Logging\Formatter\MiddagLineFormatter;
use Middag\Framework\Logging\Handler\RotatingStreamHandler;
use Middag\Framework\Logging\Processor\ActorOriginProcessor;
use Middag\Framework\Logging\Processor\RedactionProcessor;
use Middag\Framework\Logging\Redactor\DefaultSecretRedactor;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PSR-3 logger factory. Produces one cached `LoggerInterface` per
 * `(module, channel)` tuple backed by Monolog with a hour-bucket file
 * handler (`RotatingStreamHandler`), the legacy line format
 * (`MiddagLineFormatter`), and an actor/origin processor.
 *
 * When the factory is disabled (`$enabled = false`) callers receive a
 * `NullLogger` so production code paths can stay unchanged.
 *
 * Every produced logger redacts sensitive context keys before write via a
 * {@see RedactionProcessor}; bind a custom {@see SecretRedactorInterface} (4th
 * constructor argument) to extend or replace the default key list.
 *
 * @api
 */
final class LoggerFactory
{
    /** @var array<string, LoggerInterface> */
    private array $cache = [];

    private readonly SecretRedactorInterface $redactor;

    public function __construct(
        private readonly string $basePath,
        private readonly ActorResolverInterface $actor,
        private readonly OriginResolverInterface $origin,
        private readonly bool $enabled = true,
        ?SecretRedactorInterface $redactor = null,
    ) {
        $this->redactor = $redactor ?? new DefaultSecretRedactor();
    }

    /**
     * Resolves the PSR-3 logger for a `(module, channel)` tuple, defaulting
     * to `('core', 'system')`. The tuple drives the on-disk path
     * (`{basePath}/{module}/{channel}/...` via `RotatingStreamHandler`), so
     * the same tuple always yields the identical cached instance. When the
     * factory is disabled a `NullLogger` is returned (and cached) instead.
     *
     * `forChannel()` is a non-static instance method, so wire it as a DI factory
     * on the constructed service instance, not the class string, e.g.
     * `->setFactory([new Reference('Middag\Framework\Logging\LoggerFactory'), 'forChannel'])`.
     */
    public function forChannel(string $module = 'core', string $channel = 'system'): LoggerInterface
    {
        $key = $module . '|' . $channel;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if (!$this->enabled) {
            return $this->cache[$key] = new NullLogger();
        }

        $handler = new RotatingStreamHandler($this->basePath, $module, $channel);
        $handler->setFormatter(new MiddagLineFormatter());

        $logger = new Logger($module . '/' . $channel);
        $logger->pushProcessor(new RedactionProcessor($this->redactor));
        $logger->pushProcessor(new ActorOriginProcessor($this->actor, $this->origin));
        $logger->pushHandler($handler);

        return $this->cache[$key] = $logger;
    }

    /**
     * @internal convenience for disabled-logging wiring; not part of the public factory contract
     */
    public function nullLogger(): LoggerInterface
    {
        return new NullLogger();
    }

    /**
     * @internal convenience for disabled-logging wiring; not part of the public factory contract
     */
    public function disabledHandler(): NullHandler
    {
        return new NullHandler();
    }
}
