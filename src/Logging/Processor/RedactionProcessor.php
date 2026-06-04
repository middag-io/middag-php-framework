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

use Middag\Framework\Logging\Contract\SecretRedactorInterface;
use Middag\Framework\Logging\LoggerFactory;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Scrubs sensitive values out of every log record's context before it reaches
 * the handler, so passwords/tokens/PII never hit disk in plaintext.
 *
 * Delegates the decision of "what is sensitive" to a {@see SecretRedactorInterface}
 * strategy — shipped enabled by default in {@see LoggerFactory}
 * and overridable by binding a different redactor.
 *
 * @api
 */
final readonly class RedactionProcessor implements ProcessorInterface
{
    public function __construct(
        private SecretRedactorInterface $redactor,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redactor->redact($record->context));
    }
}
