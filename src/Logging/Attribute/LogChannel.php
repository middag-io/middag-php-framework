<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Attribute;

use Attribute;

/**
 * Declares the `(module, channel)` log channel a service's injected
 * `Psr\Log\LoggerInterface` should write to.
 *
 * Discovery binds the annotated service's logger argument to
 * `LoggerFactory::forChannel($module, $channel)`, so the on-disk path and the
 * Monolog channel name follow the tuple. The OSS default `LoggerFactory` is
 * disabled (a `NullLogger`) until the app enables logging; the channel binding
 * still applies, it simply routes to a null logger until then.
 *
 * Platform-agnostic and deliberately NOT Monolog's `#[WithMonologChannel]`,
 * which is a single flat channel string honoured only by the Monolog bundle.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class LogChannel
{
    public function __construct(
        public string $module = 'core',
        public string $channel = 'system',
    ) {}
}
