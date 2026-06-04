<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Processor;

use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Stamps each log record with the resolved actor and origin under `extra`.
 *
 * @api
 */
final readonly class ActorOriginProcessor implements ProcessorInterface
{
    public function __construct(
        private ActorResolverInterface $actor,
        private OriginResolverInterface $origin,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['actor'] = $this->actor->resolve();
        $record->extra['origin'] = $this->origin->resolve();

        return $record;
    }
}
