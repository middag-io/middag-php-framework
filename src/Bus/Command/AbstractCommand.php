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

use Middag\Framework\Bus\Contract\CommandInterface;

/**
 * Abstract base for module commands.
 *
 * Carries no behaviour by design: it is the stable extension point (and the
 * #[Schedule] anchor) for module commands. Implement toPayload() and
 * fromPayload() for the concrete command's payload; optionally annotate with
 * #[Schedule] for periodic cron execution.
 *
 * @api
 *
 * @example
 *   #[Schedule(minute: '0', hour: '*\/6')]
 *   final class SyncProductsCommand extends AbstractCommand {
 *       public function __construct(public readonly int $siteId) {}
 *       public function toPayload(): array { return ['site_id' => $this->siteId]; }
 *       public static function fromPayload(array $payload): static {
 *           return new static(siteId: (int) ($payload['site_id'] ?? 0));
 *       }
 *   }
 */
abstract class AbstractCommand implements CommandInterface {}
